#!/bin/bash

# Run either PHPUnit tests or PHP_CodeSniffer tests on Travis CI, depending
# on the passed in parameter.

mysql_to_ramdisk() {
    sudo service mysql stop
    sudo mv /var/lib/mysql /var/run/tmpfs
    sudo ln -s /var/run/tmpfs /var/lib/mysql
    sudo service mysql start
}

TEST_DIRS=($MODULE_DIR/tests)

case "$1" in
    PHP_CodeSniffer)
        cd $MODULE_DIR
        ./vendor/bin/phpcs
        exit $?
        ;;
    *)
        mysql_to_ramdisk
        cd $DRUPAL_DIR
        EXIT=0
        for i in ${TEST_DIRS[@]}; do
            echo "Executing tests in $i"
            $TRAVIS_BUILD_DIR/vendor/bin/phpunit -c $DRUPAL_DIR/core/phpunit.xml.dist $i || EXIT=1
        done
        exit $EXIT
esac
