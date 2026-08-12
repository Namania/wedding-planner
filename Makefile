up:
	docker compose up -d

down:
	docker compose down

logs:
	docker compose logs -f

exec:
	docker exec -it wedding-planner-php bash

fresh:
	docker exec -it wedding-planner-php php artisan migrate:fresh --seed
