# Algorithm Orders Task

## Setup
```
git clone https://github.com/killedit/2026-01-27-alphavision-task.git
2026-01-27-alphavision-task
docker compose up -d --build
```

## DB

Option 1: Connect to `alphavision-mysql-1` container:

```
docker exec -it alphavision-mysql-1 bash
mysql -u root -p
    admin123
use alphavision;
show tables;
...
```

Option 2: Create a new db connection in DBeaver.

```
Server Host:    127.0.0.1
Port:           3307
Database:       alphavision
Username:       laravel_user
Password:       user123

Driver properties:
    allowPublicKeyRetrieval     TRUE
    useSSL                      FALSE

Test Connection...
```

## Application

`http://localhost:8087/restaurant`

![Home](laravel/resources/images/2026-01-27-alphavision-task-home.png)