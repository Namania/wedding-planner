up:
	docker compose up -d

exec:
	docker exec -it wending-planner-php bash

prod-build:
	docker compose -f compose.prod.yml build

prod-up:
	docker compose -f compose.prod.yml up -d

prod-exec:
	docker exec -it wending-planner-app bash
