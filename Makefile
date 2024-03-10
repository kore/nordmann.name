develop:
	bin/develop.sh

deploy:
	rsync --delete -a ./ --exclude data --exclude logs --exclude .git --exclude id_rsa --exclude id_rsa.pub privat-web:/var/www/nordmann.name/
