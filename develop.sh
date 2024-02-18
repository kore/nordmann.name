#!/usr/bin/env bash

killbg() {
    for p in "${pids[@]}" ; do
        kill "$p";
    done
}

trap killbg EXIT

pids=()
node_modules/.bin/sass styles/app.scss:styles.css --load-path=node_modules/ --watch &
pids+=($!)

php -S localhost:8080 -t ./
