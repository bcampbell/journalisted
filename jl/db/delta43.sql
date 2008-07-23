-- add a flag to article to give status of full text indexing
ALTER TABLE article ADD COLUMN needs_indexing BOOLEAN DEFAULT true NOT NULL;

