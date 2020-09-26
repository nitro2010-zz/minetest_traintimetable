## MINETEST RAILWAY CONNECTIONS SEARCH ENGINE

**Description:**

MineTest Railway Connections Search Engine is tool which allows to search train connections between the railway stations of interest to us.
It using [MapServer](https://github.com/minetest-mapserver/mapserver) and [MapServer MOD](https://github.com/minetest-mapserver/mapserver_mod) to download train lines (defined earlier by mapserver:train blocks) and saved train lines (with distances and travel time) and connections between cities (based on train lines) to database.

It uses Floyd–Warshall algorithm to fast find path between start station and end station (show only one path), and undirected graph to find all paths between stations (note: if some city doesn't have connection with graph, it causes freezing website or show server error 503 - Service Unavailable, so Floyd–Warshall algorithm fix it).

**Depends:**

- PHP
- [MapServer ](https://github.com/minetest-mapserver/mapserver)
- [MapServer module ](https://github.com/minetest-mapserver/mapserver_mod)
- Server WWW with PHP support

**Project website:**

[Github website](https://github.com/nitro2010/minetest_traintimetable)

**License:**

CC BY 4.0

**Other information:**

- https://thenounproject.com/ - some icons are downloaded from it

**Installing & Configuration:**

1. Create new database in your database system (PostgreSQL, MySQL, SQLite is using file) and assign permissions to this database.
2. Open php.ini and check that extension selected database is enabled (uncomment line, remove ';'):
- if you choose MySQL:
> extension=pdo_mysql
- if you choose PostgreSQL:
> extension=pdo_pgsql
- if you choose SQLite:
> extension=pdo_sqlite

3. Save file and restart your web server.
4. Download project to your web server documents root directory.
5. Open minetest.php.
6. On the beginning file you have few lines to configuration.
- MINETEST_ADVTRAINS_AVG_SPEED - average speed of trains in m/s... default is 20 m/s
- MINETEST_MAPSERVER - URL to MineTest mapserver
- DB_CONNECTION - string connection to database, see above for examples.
- DB_LOGIN - login to database (sqlite don't use it - you can set to empty or null)
- DB_PASSWORD - password to database (sqlite don't use it - you can set to empty or null)
7. Save file.
8. Open console/terminal in directory where is minetest.php and type in console and confirm [ENTER]:

> php minetest.php -a createtables

It creates tables in database.

9. Type in console and confirm [ENTER]:

> php minetest.php -a mapserver -o

It download all mapserver:train blocks from MapServer and save train lines to database.

10. It's all. Open minetest.php in your web browser.

**Refesh the data about train lines in  database**

When you add/remove stations (mapserver:train block) you need to update information about it in database.

Open console where is minetest.php file
and type & confirm [ENTER]:

> php minetest.php -a mapserver -o

its delete all old the data from database and download again the data from MapServer

**Additional settings in minetest.php**

> error_reporting(0)

- 0 - don't show any errors
- E_ALL - show all errors

> set_time_limit(60)

- 60 [or any number] - max. time in seconds for executing code
- Please don't set 0 - its means any limits for executing code
- 60 is ok, you can set lower number or higher but not too high

> ini_set('memory_limit', '512M')

- set max memory which may be used by script
- 512M - 512 MegaBytes