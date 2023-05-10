FROM mysql:8.0

COPY mysql/db/schema-mysql-statistics.sql /docker-entrypoint-initdb.d
COPY mysql/db/schema-mysql.sql /docker-entrypoint-initdb.d

EXPOSE 3306
