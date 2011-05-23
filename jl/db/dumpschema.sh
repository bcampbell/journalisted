#!/bin/sh
echo "Dumping schema.sql..."
pg_dump -O -s -U jl jl >schema.sql
#echo "dumping to basedata.sql"
#pg_dump -O -a -t organisation >basedata.sql
echo "done"

