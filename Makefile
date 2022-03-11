APP:=dev
up_project: 
	@docker-compose up -d --build
install_compose: 
	@docker exec -i $(shell docker-compose -p $(APP) ps -q php_fpm) sh -c "cd server/ && composer install"
	@docker exec -i $(shell docker-compose -p $(APP) ps -q php_fpm) sh -c "php artisan key:generate"
cheack_php_fpm: 
	@echo $(shell docker-compose -p $(APP) ps -q php_fpm)	
start_project: up_project cheack_php_fpm install_compose
	@echo 'ok'