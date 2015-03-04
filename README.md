Install
===

	curl -sS https://getcomposer.org/installer | php
        ./composer.phar install

        php -dinclude_path=`pwd`/vendor/joostd/tiqr-server/libTiqr/library/tiqr -S ip:port -t www

