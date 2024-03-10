# Simple PHP ActivityPub multi-user server

Forked from https://gitlab.com/edent/activitypub-single-php-file – licensed
under AGPLv3.

It is a very simple multi-user ActivityPub server focussing on maintaining
aliases under a domain and providing additional bot accounts replaying RSS
feeds as ActivityPub feeds.

An example configuration can be found in the `users.php` file.

All data is stored in the `data` directory, so no database is required. Data
storage is not protected against concurrent writes, so this implementation can
only be used with low traffic volumes.

## Development

Run `make develop` or `bash bin/develop.sh` to start developing. This uses PHPs
internal web server and also runs the SCSS compilation in the background. The
frontend doesn't reload on CSS changes, though, so you'll have to manually
refresh the browser.

You can replay the automatically created log files to test certain
interactions. Most importantly there are some recorded interactions in the
`tests/fixtures` directory, which can be replayed like:

    bin/replay tests/fixtures/webfinger.txt

## Installation

Adapt the `users.php` as you wish and add `id_rsa` and `id_rsa.pub` files into
the root directory. The data structure in the `users.php` file is not verified,
so be careful with your modifications.

If you have users with connected RSS feeds, you might want to install a cron
job regularly fetching new feed items, like:

    23 * * * * php /path/to/bin/fetchFeeds

You can also run this command manually. By default it will fetch only new feed
items after the last post on the current account, or the feed items of the past
year, if there hasn't been a post yet. You can manually specify the date range,
as well: `bin/fetchFeeds '2 years ago'`.

## Deployment

As you can see in the `Makefile` I just deploy the code to my server using a
simple rsync command, evben while this obviously isn't atomic:

    rsync --delete -a ./ --exclude data --exclude logs --exclude .git --exclude id_rsa --exclude id_rsa.pub <host>:<path>/

