SHELL=/bin/bash

# DIRECTORIES
BASE_DIR = .
SRC_DIR = $(BASE_DIR)/src
CORE_DIR = $(SRC_DIR)/lib
CORE_API_DIR = $(SRC_DIR)/app
DOC_DIR = $(BASE_DIR)/apidoc
CONFIG_DIR = $(BASE_DIR)/config
VENDOR_DIR = $(BASE_DIR)/vendor
NODE_MODULES_DIR = $(BASE_DIR)/node_modules
TESTS_DIR = $(BASE_DIR)/tests
UNITTESTS_DIR = $(TESTS_DIR)/Unit
DIST_DIR = $(BASE_DIR)/dist
LOG_DIR = $(BASE_DIR)/log
BUILD_DIR = $(BASE_DIR)/build
APIDOC_BUILD_DIR = $(BASE_DIR)/build_apidoc
INSTALL_PREFIX = "/"

# VERSION
ifeq ($(VERSION),)
VERSION := "0.0.1"
endif

# PACKAGES
DEB = $(DIST_DIR)/balloon-$(VERSION).deb
TAR = $(DIST_DIR)/balloon-$(VERSION).tar.gz

# PHP BINARY
PHP_BIN = php
# COMPOSER STUFF
COMPOSER_BIN = composer
COMPOSER_LOCK = $(BASE_DIR)/composer.lock
# NPM STUFF
NPM_BIN = npm
# APIDOC STUFF
APIDOC_BIN = $(NODE_MODULES_DIR)/apidoc/bin/apidoc
# PHP CS FIXER STUFF
PHPCS_FIXER_SCRIPT = $(VENDOR_DIR)/bin/php-cs-fixer
PHPCS_FIXER_LOCK = $(BASE_DIR)/.php_cs.cache
# PHPUNIT STUFF
PHPUNIT_SCRIPT = $(VENDOR_DIR)/bin/phpunit
PHPUNIT_BOOTSTRAP_SCRIPT = $(UNITTESTS_DIR)/Bootstrap.php
PHPUNIT_LOCK = $(BASE_DIR)/.phpunit.lock
# PHPSTAN STUFF
PHPSTAN_SCRIPT = $(VENDOR_DIR)/bin/phpstan
PHPSTAN_LOCK = $(BASE_DIR)/.phpstan.lock

# TARGET ALIASES
INSTALL_TARGET = "$(INSTALL_PREFIX)usr/share/balloon"
NPM_TARGET = $(NODE_MODULES_DIR)
COMPOSER_TARGET = $(COMPOSER_LOCK)
APIDOC_TARGET = $(DOC_DIR)
PHPCS_CHECK_TARGET = $(PHPCS_FIXER_LOCK)
PHPUNIT_TARGET = $(PHPUNIT_LOCK)
PHPSTAN_TARGET = $(PHPSTAN_LOCK)
CHANGELOG_TARGET = $(BUILD_DIR)/DEBIAN/changelog
BUILD_TARGET = $(COMPOSER_TARGET) $(NPM_TARGET) $(PHPCS_CHECK_TARGET) $(PHPUNIT_TARGET) $(PHPSTAN_TARGET) $(APIDOC_TARGET)

# MACROS
macro_find_phpfiles = $(shell find $(1) -type f -name "*.php")

# SOURCECODE FILESETS
PHP_FILES = $(call macro_find_phpfiles,$(SRC_DIR))
PHP_CORE_FILES = $(call macro_find_phpfiles,$(CORE_DIR))
PHP_CORE_API_FILES = $(call macro_find_phpfiles,$(CORE_API_DIR))
PHP_TEST_FILES = $(call macro_find_phpfiles,$(TESTS_DIR))
PHP_UNITTEST_FILES = $(call macro_find_phpfiles,$(UNITTESTS_DIR))

# TARGETS
.PHONY: all
all: build


.PHONY: clean
clean: mostlyclean
	@-test ! -d $(VENDOR_DIR) || rm -rfv $(VENDOR_DIR)/*


.PHONY: mostlyclean
mostlyclean:
	@-test ! -f $(TAR) || rm -fv $(TAR)
	@-test ! -d $(BUILD_DIR) || rm -rfv $(BUILD_DIR)
	@-test ! -d $(NODE_MODULES_DIR) || rm -rfv $(NODE_MODULES_DIR)
	@-test ! -f $(DIST_DIR)/*.deb || rm -fv $(DIST_DIR)/*.deb
	@-test ! -f $(COMPOSER_LOCK) || rm -fv $(COMPOSER_LOCK)
	@-test ! -f $(PHPCS_FIXER_LOCK) || rm -fv $(PHPCS_FIXER_LOCK)
	@-test ! -f $(PHPUNIT_LOCK) || rm -fv $(PHPUNIT_LOCK)
	@-test ! -f $(PHPSTAN_LOCK) || rm -fv $(PHPSTAN_LOCK)


.PHONY: deps
deps: composer npm


.PHONY: build
build: $(BUILD_TARGET)


.PHONY: dist
dist: tar deb


.PHONY: deb
deb: $(DIST_DIR)/balloon-$(VERSION).deb $(DIST_DIR)/balloon-apidoc-$(VERSION).deb

$(DIST_DIR)/balloon-$(VERSION).deb: $(CHANGELOG_TARGET) $(BUILD_TARGET)
	$(COMPOSER_BIN) update --no-dev
	@mkdir -p $(BUILD_DIR)/DEBIAN
	@cp $(BASE_DIR)/packaging/debian/control $(BUILD_DIR)/DEBIAN/control
	@cp $(BASE_DIR)/packaging/debian/postinst $(BUILD_DIR)/DEBIAN/postinst
	@sed -i s/'{version}'/$(VERSION)/g $(BUILD_DIR)/DEBIAN/control
	@mkdir -p $(BUILD_DIR)/usr/share/balloon/src
	@mkdir -p $(BUILD_DIR)/usr/share/balloon/scripts
	@mkdir -p $(BUILD_DIR)/usr/share/balloon/bin/console
	@mkdir -p $(BUILD_DIR)/etc/balloon
	@rsync -a --exclude='.git' $(VENDOR_DIR) $(BUILD_DIR)/usr/share/balloon
	@cp -Rp $(DOC_DIR) $(BUILD_DIR)/usr/share/balloon
	@cp  $(BASE_DIR)/packaging/balloon-jobs.service.systemd $(BUILD_DIR)/usr/share/balloon/scripts
	@cp  $(BASE_DIR)/packaging/balloon-jobs.service.upstart $(BUILD_DIR)/usr/share/balloon/scripts
	@cp -Rp $(SRC_DIR)/cgi-bin/cli.php $(BUILD_DIR)/usr/share/balloon/bin/console/ballooncli
	@cp -Rp $(SRC_DIR)/httpdocs $(BUILD_DIR)/usr/share/balloon/bin
	@cp -Rp $(SRC_DIR)/{lib,app} $(BUILD_DIR)/usr/share/balloon/src
	@cp -Rp $(SRC_DIR)/.container.config.php $(BUILD_DIR)/usr/share/balloon/src
	@mkdir -p $(BUILD_DIR)/etc/balloon
	@cp $(CONFIG_DIR)/config.yaml.dist $(BUILD_DIR)/etc/balloon
	@-test -d $(DIST_DIR) || mkdir $(DIST_DIR)
	@dpkg-deb --build $(BUILD_DIR) $@
	$(COMPOSER_BIN) update

$(DIST_DIR)/balloon-apidoc-$(VERSION).deb: $(CHANGELOG_TARGET) $(BUILD_TARGET)
	@mkdir -p $(APIDOC_BUILD_DIR)/DEBIAN
	@cp $(BASE_DIR)/packaging/debian/control-apidoc $(APIDOC_BUILD_DIR)/DEBIAN/control
	@sed -i s/'{version}'/$(VERSION)/g $(APIDOC_BUILD_DIR)/DEBIAN/control
	@mkdir -p $(APIDOC_BUILD_DIR)/usr/share/balloon-apidoc
	@cp -Rp $(DOC_DIR)/* $(APIDOC_BUILD_DIR)/usr/share/balloon-apidoc
	@-test -d $(DIST_DIR) || mkdir $(DIST_DIR)
	@dpkg-deb --build $(APIDOC_BUILD_DIR) $@

.PHONY: tar
tar: $(TAR)

$(TAR): $(BUILD_TARGET)
	$(COMPOSER_BIN) update --no-dev
	@-test ! -f $(TAR) || rm -fv $(TAR)
	@-test -d $(DIST_DIR) || mkdir $(DIST_DIR)
	@-test ! -d $(BUILD_DIR) || rm -rfv $(BUILD_DIR)
	@mkdir $(BUILD_DIR)
	@cp -Rp $(CONFIG_DIR) $(BUILD_DIR)
	@cp -Rp $(VENDOR_DIR) $(BUILD_DIR)
	@cp -Rp $(SRC_DIR) $(BUILD_DIR)
	@cp -Rp $(DOC_DIR) $(BUILD_DIR)
	@mkdir $(BUILD_DIR)/log

	@tar -czf $(TAR) -C $(BUILD_DIR) .
	@rm -rf $(BUILD_DIR)

	$(COMPOSER_BIN) update
	@touch $@


.PHONY: changelog
changelog: $(CHANGELOG_TARGET)

$(CHANGELOG_TARGET): CHANGELOG.md
	@-test -d $(@D) || mkdir -p $(@D)
	@v=""
	@stable="stable"
	@author=""
	@date=""
	@changes=""
	@-test ! -f $@ || rm $@

	@while read l; \
	do \
		if [ "$${l:0:2}" == "##" ]; \
		then \
	 		if [ "$$v" != "" ]; \
	 		then \
	 			echo "balloon ($$v) $$stable; urgency=low" >> $@; \
	 			echo -e "$$changes" >> $@; \
	 			echo >>  $@; \
	 			echo " -- $$author  $$date" >> $@; \
	 			echo >>  $@; \
	 			v=""; \
	 			stable="stable"; \
	 			author=";" \
	 			date=";" \
	 			changes=""; \
	 		fi; \
	 		v=$${l:3}; \
			if [[ "$$v" == *"RC"* ]]; \
	 	 	then \
	 			stable="unstable"; \
	 		elif [[ "$$v" == *"BETA"* ]]; \
	 		then \
	 			stable="unstable"; \
	 		elif [[ "$$v" == *"ALPHA"* ]]; \
	 		then \
	 			stable="unstable"; \
	 		elif [[ "$$v" == *"dev"* ]]; \
			then \
	 			stable="unstable"; \
	 		fi \
	 	elif [ "$${l:0:5}" == "**Mai" ]; \
	 	then \
	 		p1=`echo $$l | cut -d '>' -f1`; \
	 		p2=`echo $$l | cut -d '>' -f2`; \
	 		author="$${p1:16}>"; \
	 		date=$${p2:13}; \
	 		date=`date -d"$$date" +'%a, %d %b %Y %H:%M:%S %z'`; \
	 	elif [ "$${l:0:2}" == "* " ]; \
	 	then \
	 		changes="  $$changes\n  $$l"; \
	 	fi; \
	done < $<
	@echo generated $@ from $<


.PHONY: apidoc
apidoc: $(APIDOC_TARGET)

$(APIDOC_TARGET): $(APIDOC_BIN) $(PHP_CORE_API_FILES)
	$(APIDOC_BIN) -i $(CORE_API_DIR) -o $(DOC_DIR)
	@touch $@


.PHONY: composer
composer: $(COMPOSER_TARGET)

$(COMPOSER_TARGET) $(PHPCS_FIXER_SCRIPT) $(PHPUNIT_SCRIPT) $(PHPSTAN_SCRIPT): $(BASE_DIR)/composer.json
	$(COMPOSER_BIN) update
	@touch $@


.PHONY: npm
npm: $(NPM_TARGET)

$(NPM_TARGET) $(APIDOC_BIN): $(BASE_DIR)/package.json
	@test "`$(NPM_BIN) install --dry-run 2>&1 >/dev/null | grep Failed`" == ""
	$(NPM_BIN) update
	@touch $@


.PHONY: phpcs
phpcs: $(PHPCS_CHECK_TARGET)

$(PHPCS_CHECK_TARGET): $(PHPCS_FIXER_SCRIPT) $(PHP_FILES) $(COMPOSER_LOCK)
	$(PHP_BIN) $(PHPCS_FIXER_SCRIPT)  fix --config=.php_cs.dist -v --dry-run --allow-risky=yes --stop-on-violation --using-cache=no
	@touch $@


.PHONY: test
test: $(PHPUNIT_TARGET)

.PHONY: phpunit
phpunit: $(PHPUNIT_TARGET)

$(PHPUNIT_TARGET): $(PHPUNIT_SCRIPT) $(PHP_FILES) $(PHP_UNITTEST_FILES)
	$(PHP_BIN) $(PHPUNIT_SCRIPT) --stderr --debug -c phpunit.xml
	@touch $@

.PHONY: phpstan
phpstan: $(PHPSTAN_TARGET)

$(PHPSTAN_TARGET): $(PHPSTAN_SCRIPT) $(PHP_FILES) $(PHP_TEST_FILES)
	$(PHP_BIN) $(PHPSTAN_SCRIPT) analyse -c phpstan.neon $(SRC_DIR) $(TESTS_DIR)
	@touch $@

.PHONY: install
install: $(INSTALL_TARGET)

$(INSTALL_TARGET): $(BUILD_TARGET)
	$(COMPOSER_BIN) update --no-dev
	@mkdir -p $(BUILD_DIR)/usr/share/balloon/src
	@mkdir -p $(BUILD_DIR)/usr/share/balloon/scripts
	@mkdir -p $(BUILD_DIR)/usr/share/balloon/bin/console
	@mkdir -p $(BUILD_DIR)/etc/balloon
	@rsync -a --exclude='.git' $(VENDOR_DIR) $(BUILD_DIR)/usr/share/balloon
	@cp -Rp $(DOC_DIR) $(BUILD_DIR)/usr/share/balloon
	@cp  $(BASE_DIR)/packaging/balloon-jobs.service.systemd $(BUILD_DIR)/usr/share/balloon/scripts
	@cp  $(BASE_DIR)/packaging/balloon-jobs.service.upstart $(BUILD_DIR)/usr/share/balloon/scripts
	@cp -Rp $(SRC_DIR)/cgi-bin/cli.php $(BUILD_DIR)/usr/share/balloon/bin/console/ballooncli
	@cp -Rp $(SRC_DIR)/httpdocs $(BUILD_DIR)/usr/share/balloon/bin
	@cp -Rp $(SRC_DIR)/{lib,app} $(BUILD_DIR)/usr/share/balloon/src
	@cp -Rp $(SRC_DIR)/.container.config.php $(BUILD_DIR)/usr/share/balloon/src
	@mkdir -p $(BUILD_DIR)/etc/balloon
	@cp $(CONFIG_DIR)/config.yaml.dist $(BUILD_DIR)/etc/balloon
	@cp -Rp $(BUILD_DIR)/* $(INSTALL_PREFIX)
	$(COMPOSER_BIN) update
