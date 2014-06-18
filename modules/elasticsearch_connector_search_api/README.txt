You can use a raw query like this:

$id = 'default_node_index';
$index = search_api_index_load($id);
$query = $index->query(array(
  'parse mode' => 'terms',
));

$query->condition('title', 'Tation');
$or = $query->createFilter('OR');
$or->condition('nid', '1');
$or->condition('nid', '2');
$query->filter($or);
$result = $query->execute();
var_dump($result);exit;