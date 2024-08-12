develop:
	bin/develop.sh

deploy:
	rsync --delete -a ./ --exclude data --exclude logs --exclude .git --exclude id_rsa --exclude id_rsa.pub web@nordmann.name:/var/www/nordmann.name/
