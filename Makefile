develop:
	bin/develop.sh

deploy:
	rsync --delete -a ./ --exclude logs --exclude .git privat-web:/var/www/nordmann.name/
