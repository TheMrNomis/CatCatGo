database: makecache.sql
	rm cache.db
	sqlite3 cache.db < makecache.sql
