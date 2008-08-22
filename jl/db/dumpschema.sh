#!/bin/sh
echo "Dumping schema.sql..."
pg_dump -s -U mst mst >schema.sql
echo "dumping to basedata.sql"
pg_dump -a -t organisation -U mst mst >basedata.sql
echo "done"

