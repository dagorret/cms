#!/bin/bash
docker compose exec -u 1000 -it cms-php php "$@"
