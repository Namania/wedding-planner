up:
	docker compose up -d

exec:
	docker exec -it wedding-planner-php bash

prod-build:
	docker compose -f compose.prod.yml build

prod-up:
	docker compose -f compose.prod.yml up -d

prod-exec:
	docker exec -it wedding-planner-app bash
