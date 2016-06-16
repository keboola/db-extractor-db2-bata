# DB2 DB Extractor (for Bata)

This is extractor is a fork of db-extractor-db2 using driver for connecting to server running on AS400 machine.
Therefore there is no testing environment running. There is no dockerized AS400 DB2 server :(

## Example configuration


    {
      "db": {
        "driver": "db2",
        "host": "HOST",
        "port": "PORT",
        "database": "DATABASE",
        "user": "USERNAME",
        "password": "PASSWORD",
        "ssh": {
          "enabled": true,
          "keys": {
            "private": "ENCRYPTED_PRIVATE_SSH_KEY",
            "public": "PUBLIC_SSH_KEY"
          },
          "sshHost": "PROXY_HOSTNAME"
        }
      },
      "tables": [
        {
          "name": "employees",
          "query": "SELECT * FROM employees",
          "outputTable": "in.c-main.employees",
          "incremental": false,
          "enabled": true,
          "primaryKey": null
        }
      ]
    }
