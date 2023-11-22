<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Doctrine\DBAL\Exception as DBALException;
use PHPCR\PropertyType;
use PHPCR\RepositoryException;
use PHPCR\Util\PathHelper;

$xml = \file_get_contents(__DIR__ . '/../var/homepage.xml');
$props = \explode("\n", \file_get_contents(__DIR__ . '/../var/homepage-props.csv'));

$deletePropertyPaths = [];
foreach ($props as $prop) {
    if (!\str_starts_with($prop, 'i18n:de_ch')) {
        continue;
    }

    $deletePropertyPaths[] = '/cmf/longines/contents/' . $prop;
}

function groupByNode($deletePropertyPaths): array { // Helper method
    $grouped = [];
    foreach ($deletePropertyPaths as $path) {
        $nodePath = PathHelper::getParentPath($path);
        $propertyName = PathHelper::getNodeName($path);

        $grouped[$nodePath][$propertyName] = $path;
    }

    return $grouped;
}

// ---------- Start Legacy ----------
function deleteProperty(string $xml, string $path) {
    $dom = new \DOMDocument('1.0', 'UTF-8');
    $dom->loadXML($xml);
    $xpath = new \DomXpath($dom);

    $refernceTables = [
        PropertyType::REFERENCE => 'phpcr_nodes_references',
        PropertyType::WEAKREFERENCE => 'phpcr_nodes_weakreferences',
    ];

    $queries = [];

    $found = false;
    $propertyName = PathHelper::getNodeName($path);
    foreach ($xpath->query(sprintf('//*[@sv:name="%s"]', $propertyName)) as $propertyNode) {
        $found = true;
        // would be nice to have the property object to ask for type
        // but its in state deleted, would mean lots of refactoring
        if ($propertyNode->hasAttribute('sv:type')) {
            $type = strtolower($propertyNode->getAttribute('sv:type'));
            if (in_array($type, ['reference', 'weakreference'])) {
                $table = $refernceTables['reference' === $type ? PropertyType::REFERENCE : PropertyType::WEAKREFERENCE];
                try {
                    $query = "DELETE FROM $table WHERE source_id = ? AND source_property_name = ?";

                    $queries[] = $query;
                    // $this->getConnection()->executeUpdate($query, [$nodeId, $propertyName]);
                } catch (DBALException $e) {
                    throw new RepositoryException(
                        'Unexpected exception while cleaning up deleted nodes',
                        $e->getCode(),
                        $e
                    );
                }
            }
        }

        $propertyNode->parentNode->removeChild($propertyNode);
    }

    return [$dom->saveXML(), $queries];
}

function deleteProperties(string $xml, array $deletePropertyPaths): void {
    foreach ($deletePropertyPaths as $path) {
        [$xml, $queries] = deleteProperty($xml, $path);

        echo $path . ' ' . \count($queries) . PHP_EOL;
    }

    \file_put_contents(__DIR__ . '/../var/cache.xml');
}

// ---------- End Legacy ----------

// ---------- Start Single DOMDocument ----------
function deletePropertiesSingleDOMDocument(string $xml, array $paths) {
    $dom = new \DOMDocument('1.0', 'UTF-8');
    $dom->loadXML($xml);
    $xpath = new \DomXpath($dom);

    $refernceTables = [
        PropertyType::REFERENCE => 'phpcr_nodes_references',
        PropertyType::WEAKREFERENCE => 'phpcr_nodes_weakreferences',
    ];

    foreach ($paths as $path) {
        $propertyName = PathHelper::getNodeName($path);
        $found = false;
        $queries = [];

        foreach ($xpath->query(sprintf('//*[@sv:name="%s"]', $propertyName)) as $propertyNode) {
            $found = true;
            // would be nice to have the property object to ask for type
            // but its in state deleted, would mean lots of refactoring
            if ($propertyNode->hasAttribute('sv:type')) {
                $type = strtolower($propertyNode->getAttribute('sv:type'));
                if (in_array($type, ['reference', 'weakreference'])) {
                    $table = $refernceTables['reference' === $type ? PropertyType::REFERENCE : PropertyType::WEAKREFERENCE];
                    try {
                        $query = "DELETE FROM $table WHERE source_id = ? AND source_property_name = ?";

                        $queries[] = $query;
                        // $this->getConnection()->executeUpdate($query, [$nodeId, $propertyName]);
                    } catch (DBALException $e) {
                        throw new RepositoryException(
                            'Unexpected exception while cleaning up deleted nodes',
                            $e->getCode(),
                            $e
                        );
                    }
                }
            }

            $propertyNode->parentNode->removeChild($propertyNode);

            echo $path . ' ' . \count($queries) . PHP_EOL;
        }
    }

    return $dom->saveXML();
}

function deleteSingleDOMDocument(string $xml, array $deletePropertyPaths): void {
    $nodes = groupByNode($deletePropertyPaths);
    \assert(\count($nodes) === 1); // expect currently always one node

    foreach ($nodes as $node => $paths) {
        $xml = deletePropertiesSingleDOMDocument($xml, $paths);
    }

    \file_put_contents(__DIR__ . '/../var/cache.xml', $xml);
}

// ---------- End Single DOMDocument ----------

// ---------- Start Single DOMQuery ----------
function deletePropertiesSingleDOMQuery(string $xml, array $paths) {
    $dom = new \DOMDocument('1.0', 'UTF-8');
    $dom->loadXML($xml);
    $xpath = new \DomXpath($dom);

    $refernceTables = [
        PropertyType::REFERENCE => 'phpcr_nodes_references',
        PropertyType::WEAKREFERENCE => 'phpcr_nodes_weakreferences',
    ];

    $propertyNames = \array_keys($paths);

    // doing a single query without chunking requires about query time of 1m 28s

    $domQuery = sprintf(
        '//*[%s]',
        implode(' or ', array_map(function ($name) {
            return sprintf('@sv:name="%s"', $name);
        }, $propertyNames))
    );
    $propertyNodes = $xpath->query($domQuery);

    foreach ($propertyNodes as $propertyNode) {
        $propertyName = $propertyNode->getAttribute('sv:name');
        $queries = [];

        $found = true;
        // would be nice to have the property object to ask for type
        // but its in state deleted, would mean lots of refactoring
        if ($propertyNode->hasAttribute('sv:type')) {
            $type = strtolower($propertyNode->getAttribute('sv:type'));
            if (in_array($type, ['reference', 'weakreference'])) {
                $table = $refernceTables['reference' === $type ? PropertyType::REFERENCE : PropertyType::WEAKREFERENCE];
                try {
                    $query = "DELETE FROM $table WHERE source_id = ? AND source_property_name = ?";

                    $queries[] = $query;
                    // $this->getConnection()->executeUpdate($query, [$nodeId, $propertyName]);
                } catch (DBALException $e) {
                    throw new RepositoryException(
                        'Unexpected exception while cleaning up deleted nodes',
                        $e->getCode(),
                        $e
                    );
                }
            }
        }

        $propertyNode->parentNode->removeChild($propertyNode);

        echo $propertyName . ' ' . \count($queries) . PHP_EOL;
    }

    return $dom->saveXML();
}

function deleteSingleDOMQuery(string $xml, array $deletePropertyPaths): void {
    $nodes = groupByNode($deletePropertyPaths);
    \assert(\count($nodes) === 1); // expect currently always one node

    foreach ($nodes as $node => $paths) {
        $xml = deletePropertiesSingleDOMQuery($xml, $paths);
    }

    \file_put_contents(__DIR__ . '/../var/cache.xml', $xml);
}

// ---------- End Single DOMDocument ----------

// ---------- Start Single DOMQuery Chunk ----------
function deletePropertiesSingleDOMQueryChunk(string $xml, array $paths) {
    $dom = new \DOMDocument('1.0', 'UTF-8');
    $dom->loadXML($xml);
    $xpath = new \DomXpath($dom);

    $refernceTables = [
        PropertyType::REFERENCE => 'phpcr_nodes_references',
        PropertyType::WEAKREFERENCE => 'phpcr_nodes_weakreferences',
    ];

    $propertyNames = \array_keys($paths);
    $chunks = array_chunk($propertyNames, 100); // Adjust the chunk size based on your needs

    foreach ($chunks as $propertyNames) {
        $domQuery = sprintf(
            '//*[%s]',
            implode(' or ', array_map(function ($name) {
                return sprintf('@sv:name="%s"', $name);
            }, $propertyNames))
        );
        $propertyNodes = $xpath->query($domQuery);

        foreach ($propertyNodes as $propertyNode) {
            $propertyName = $propertyNode->getAttribute('sv:name');
            $queries = [];

            $found = true;
            // would be nice to have the property object to ask for type
            // but its in state deleted, would mean lots of refactoring
            if ($propertyNode->hasAttribute('sv:type')) {
                $type = strtolower($propertyNode->getAttribute('sv:type'));
                if (in_array($type, ['reference', 'weakreference'])) {
                    $table = $refernceTables['reference' === $type ? PropertyType::REFERENCE : PropertyType::WEAKREFERENCE];
                    try {
                        $query = "DELETE FROM $table WHERE source_id = ? AND source_property_name = ?";

                        $queries[] = $query;
                        // $this->getConnection()->executeUpdate($query, [$nodeId, $propertyName]);
                    } catch (DBALException $e) {
                        throw new RepositoryException(
                            'Unexpected exception while cleaning up deleted nodes',
                            $e->getCode(),
                            $e
                        );
                    }
                }
            }

            $propertyNode->parentNode->removeChild($propertyNode);

            echo $propertyName . ' ' . \count($queries) . PHP_EOL;
        }
    }

    return $dom->saveXML();
}

function deleteSingleDOMQueryChunk(string $xml, array $deletePropertyPaths): void {
    $nodes = groupByNode($deletePropertyPaths);
    \assert(\count($nodes) === 1); // expect currently always one node

    foreach ($nodes as $node => $paths) {
        $xml = deletePropertiesSingleDOMQueryChunk($xml, $paths);
    }

    \file_put_contents(__DIR__ . '/../var/cache.xml', $xml);
}

// ---------- End Single DOMDocument ----------

switch ($argv[1] ?? 'legacy') {
    case 'legacy':
        // 6m 24s
        deleteProperties($xml, $deletePropertyPaths);
        break;
    case 'single_dom_document':
        // 2m 25s
        deleteSingleDOMDocument($xml, $deletePropertyPaths);
        break;
    case 'single_dom_query':
        // 1m 28s
        deleteSingleDOMQuery($xml, $deletePropertyPaths);
        break;
    case 'single_dom_query_chunk':
        // 1m 28s (250 Chunks)
        // 1m 28s (100 Chunks)
        // 1m 33s (10 Chunks)
        deleteSingleDOMQueryChunk($xml, $deletePropertyPaths);
        break;
}
