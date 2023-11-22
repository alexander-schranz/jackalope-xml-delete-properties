# Jackalope Analyse deletion of properties

The benchmark requires 2 files:

 - `var/homepage.xml` - the xml of the node where we want to remove properties
 - `var/props.csv` - the props names which we want remove from the given xml

## Different commands:

```bash
php src/remover.php legacy
php src/remover.php single_dom_document
php src/remover.php single_dom_query
php src/remover.php single_dom_query_chunk
```

## Results

| Command                | Time   |
|------------------------|--------|
| legacy                 | 6m 24s |
| single_dom_document    | 2m 25s |
| single_dom_query       | 1m 30  |
| single_dom_query_chunk | 6m 24s |
