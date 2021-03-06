<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Converter;

class Result
{
    /**
     * Stream.
     *
     * @var resource
     */
    protected $stream;

    /**
     * Path.
     *
     * @var string
     */
    protected $path;

    /**
     * Create result.
     *
     * @param null|mixed $resource
     */
    public function __construct(string $path, $resource = null)
    {
        $this->path = $path;
        $this->stream = $resource;
    }

    /**
     * Get path.
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Open stream.
     *
     * @return resource
     */
    public function getStream()
    {
        if (null === $this->stream) {
            return $this->stream = fopen($this->path, 'r');
        }

        return $this->stream;
    }

    /**
     * Get contents.
     */
    public function getContents(): string
    {
        return file_get_contents($this->path);
    }
}
