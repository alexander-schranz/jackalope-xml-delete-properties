# Jackalope Doctrine DBAL Analyse deletion of properties

The benchmark requires 2 files:

 - `var/props.xml` - the xml of the node where we want to remove properties (SELECT props FROM phpcr_nodes WHERE identifier = ?)
 - `var/props.csv` - the props names which we want remove from the given xml (per line one property name)

## Different commands:

```bash
php src/remover.php legacy
php src/remover.php single_dom_document
php src/remover.php single_dom_query
php src/remover.php single_dom_query_chunk
php src/remover.php xml_parse
```

## Results

### ~70000 properties (~12.5MB) remove ~1700 props

Run on a MacBook Pro (16", 2021) Apple M1 Pro 32 GB:

| Command                | Time   | Memory |
|------------------------|--------|--------|
| legacy                 | 6m 29s | 48 MB  |
| single_dom_document    | 2m 25s | 35 MB  |
| single_dom_query       | 1m 30s | 37 MB  |
| single_dom_query_chunk | 1m 29s | 35 MB  |
| xml_parse              | ~1s    | 35 MB  |

`legacy`: is the `1.9.0` version: https://github.com/jackalope/jackalope-doctrine-dbal/blob/f7b286f388e0d3a42497c29e597756d6e346fea5/src/Jackalope/Transport/DoctrineDBAL/Client.php#L1804
`single_dom_document`: should represent the state of `2.0.0-beta2` version after: https://github.com/jackalope/jackalope-doctrine-dbal/pull/423/files

**Blackfire**

I did not use Blackfire for benchmarking as it did show in past benchmark where `xml_parse`
can not be good profiled as having a lot of callback method being called via `xml_set_element_handler` and `xml_set_character_data_handler`.
So profiling takes more time as processing things as Blackfire need to log every method call.
Instead I depend on classic benchmarking via time() and memory_get_peak_usage(true) measures.

### Required changes for improvements

#### A: Group Properties

The most important thing is that we remove all properties at once instead of calling `saveXML` after each property removal.

For this we mostly would require first group all `deleteProperties` by its `node`:

```php
function groupByNode($deletePropertyPaths): array {
    $grouped = [];
    foreach ($deletePropertyPaths as $path) {
        $nodePath = PathHelper::getParentPath($path);
        $propertyName = PathHelper::getNodeName($path);

        $grouped[$nodePath][] = $propertyName;
    }

    return $grouped;
}
```

Then we load the single `node` remove all the properties and save the xml once via `saveXML`.

#### B: Grouped Reference delete queries

The `queries` to remove references should also be grouped and best a single query be send to delete the references
instead of one query per reference.

The queries are currently ignored in the benchmark as it is focused on XML manipulation.

#### C: Replace DOMDocument with xml_parse

DOMDocument is bad for performance and should be avoided.
The `xml_parse` as it allows us to streamed reading the xml and skip the properties which we want to remove.
The [`XmlPropsRemover`](src/XmlPropsRemover.php) is an example how this could be done.

#### D: TODO

 - [x] xml_parse escape attributes name correctly
 - [x] xml_parse escape attributes value correctly
 - [x] xml_parse data content correctly
 - [ ] xml_parse add queries to remove references (is for all the same so it should not matter)
 - [x] xml_parse make sure the output is the same as for DOMDocument
    - [x] self closing tags
    - [x] escape data

~~currently there is a little difference in the 2 printed xmls:~~

```
-rw-r--r--   1 staff  staff  11940901 22 Nov 23:38 removed_legacy.xml
-rw-r--r--   1 staff  staff  12002548 22 Nov 23:39 removed_xml_parse.xml
```

Update `xml_parse` variant now has the same output as the previous DOMDocument version:

```
-rw-r--r--   1 staff  staff  11940901 22 Nov 23:33 removed_legacy.xml
-rw-r--r--   1 staff  staff  11940901 23 Nov 00:18 removed_xml_parse.xml
-rw-r--r--   1 staff  staff  12009559 22 Nov 23:45 removed_legacy_pretty.xml
-rw-r--r--   1 staff  staff  12009559 23 Nov 00:18 removed_xml_parse_pretty.xml
```
