#!/bin/sh
set -eu

MYSQL_PWD=$(cat /run/andina_root/root_password)
export MYSQL_PWD

if [ "$#" -eq 0 ]; then
    set -- "$MYSQL_DATABASE"
fi

exec mysql -u root "$@"
