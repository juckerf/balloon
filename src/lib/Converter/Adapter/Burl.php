<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Converter\Adapter;

use Balloon\Converter\Exception;
use Balloon\Converter\Result;
use Balloon\Filesystem\Node\File;
use GuzzleHttp\ClientInterface as GuzzleHttpClientInterface;
use GuzzleHttp\Psr7\StreamWrapper;
use Imagick;
use Psr\Log\LoggerInterface;

class Burl implements AdapterInterface
{
    /**
     * Preview format.
     */
    const PREVIEW_FORMAT = 'png';

    /**
     * preview max size.
     *
     * @var int
     */
    protected $preview_max_size = 500;

    /**
     * GuzzleHttpClientInterface.
     *
     * @var GuzzleHttpClientInterface
     */
    protected $client;

    /**
     * LoggerInterface.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Browserlerss microservice url.
     *
     * @var string
     */
    protected $browserlessUrl = 'https://chrome.browserless.io';

    /**
     * Timeout.
     *
     * @var string
     */
    protected $timeout = '10';

    /**
     * Tmp.
     *
     * @var string
     */
    protected $tmp = '/tmp';

    /**
     * Formats.
     *
     * @var array
     */
    protected $formats = [
        'burl' => 'application/vnd.balloon.burl',
    ];

    /**
     * One way formats.
     *
     * @param array
     */
    protected $target_formats = [
        'pdf' => 'application/pdf',
    ];

    /**
     * Initialize.
     *
     * @param iterable $config
     */
    public function __construct(GuzzleHttpClientInterface $client, LoggerInterface $logger, ?Iterable $config = null)
    {
        $this->client = $client;
        $this->logger = $logger;
        $this->setOptions($config);
    }

    /**
     * Set options.
     *
     * @param iterable $config
     */
    public function setOptions(Iterable $config = null): AdapterInterface
    {
        if (null === $config) {
            return $this;
        }

        foreach ($config as $option => $value) {
            switch ($option) {
                case 'browserlessUrl':
                    if (!filter_var($value, FILTER_VALIDATE_URL)) {
                        throw new Exception('browserlessUrl option must be a valid url to a browserless instance');
                    }

                    $this->browserlessUrl = (string) $value;

                    break;
                case 'tmp':
                    if (!is_writeable($value)) {
                        throw new Exception('tmp option must be a writable directory');
                    }

                    $this->tmp = (string) $value;

                    break;
                case 'timeout':
                    if (!is_numeric($value)) {
                        throw new Exception('timeout option must be a number');
                    }

                    $this->timeout = (string) $value;

                    break;
                case 'preview_max_size':
                    $this->preview_max_size = (int) $value;

                    break;
                default:
                    throw new Exception('invalid option '.$option.' given');
            }
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function match(File $file): bool
    {
        foreach ($this->formats as $format => $mimetype) {
            if ($file->getContentType() === $mimetype) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function matchPreview(File $file): bool
    {
        return $this->match($file);
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedFormats(File $file): array
    {
        return array_keys($this->target_formats);
    }

    /**
     * {@inheritdoc}
     */
    public function createPreview(File $file): Result
    {
        $response = $this->client->request(
            'POST',
            $this->browserlessUrl . '/screenshot',
            [
                'json' => [
                    'url'       => \stream_get_contents($file->get()),
                    'options'   => [
                        'fullPage' => true,
                        'type'     => 'jpeg',
                        'quality'  => 75,
                    ],
                ]
            ]
        );

        $desth = tmpfile();
        $dest = stream_get_meta_data($desth)['uri'];

        stream_copy_to_stream(StreamWrapper::getResource($response->getBody()), $desth);

        if (!file_exists($dest) || filesize($dest) <= 0) {
            throw new Exception('failed create prevew');
        }

        return new Result($dest, $desth);
    }

    /**
     * {@inheritdoc}
     */
    public function convert(File $file, string $format): Result
    {
        $response = $this->client->request(
            'POST',
            $this->browserlessUrl . '/pdf',
            [
                'json' => [
                    'url'       => \stream_get_contents($file->get()),
                    'options'   => [
                        'printBackground'   => false,
                        'format'            => 'A4',
                    ],
                ]
            ]
        );

        $desth = tmpfile();
        $dest = stream_get_meta_data($desth)['uri'];

        stream_copy_to_stream($response->getBody(), $desth);

        if (!file_exists($dest) || filesize($dest) <= 0) {
            throw new Exception('failed create prevew');
        }

        return new Result($dest, $desth);
    }
}
