#!/bin/sh
set -eu

archivo_aplicacion=/run/andina/db_password
archivo_root=/run/andina_root/root_password

mkdir -p /run/andina /run/andina_root

preparar_clave() {
    archivo=$1
    proporcionada=$2
    nombre=$3

    if [ -s "$archivo" ]; then
        if [ -n "$proporcionada" ] && [ "$(cat "$archivo")" != "$proporcionada" ]; then
            echo "La clave de $nombre difiere de la guardada para este volumen. Revisa tu .env." >&2
            exit 1
        fi
        return
    fi

    if [ -d /var/lib/mysql/mysql ] && [ -z "$proporcionada" ]; then
        echo "La base existente necesita su clave de $nombre en .env para crear el archivo privado." >&2
        exit 1
    fi

    if [ -n "$proporcionada" ]; then
        clave=$proporcionada
    else
        clave=$(openssl rand -hex 32)
    fi
    (umask 077; printf '%s' "$clave" > "$archivo")
}

preparar_clave "$archivo_aplicacion" "${ANDINA_DB_PASSWORD:-}" 'aplicación'
preparar_clave "$archivo_root" "${ANDINA_ROOT_PASSWORD:-}" 'administración de MySQL'

chown mysql:33 "$archivo_aplicacion"
chmod 0440 "$archivo_aplicacion"
chown mysql:mysql "$archivo_root"
chmod 0400 "$archivo_root"

unset ANDINA_DB_PASSWORD ANDINA_ROOT_PASSWORD
exec /usr/local/bin/docker-entrypoint.sh "$@"
