<?php

/**
 * Data source plugin for easyLOD that generates XML for an item in
 * a MySQL database. The database has one table:
 *
 * +----+------------+--------------------------------------+----------------------+------+
 * | id | identifier | title                                | description          | date |
 * +----+------------+--------------------------------------+----------------------+------+
 * |  1 | 0002       | A Scanner Darkly                     | Don't do drugs.      | 1977 |
 * |  2 | 0003       | The Man in the High Castle           | Glad we won the war. | 1962 |
 * |  3 | 0001       | Do Androids Dream of Electric Sheep? | Lots of robots.      | 1968 |
 * +----+------------+--------------------------------------+----------------------+------+
 *
 *  Distributed under the MIT License, http://opensource.org/licenses/MIT.
 */

/**
 * Required function. Checks to see if a configuration for this plugin
 * exists in the $plugins array, and if not, returns the configuration
 * defined here.
 *
 * @param string $namespace
 *  The namespace portion of the request URI.
 *
 * @return
 *  An associative array containing this plugin's configuration data
 *  in key => value pairs.
 */
function dataSourceConfig($namespace) {
  // First check to see if this configration is being
  // overridden in plugins.php.
  global $plugins;
  if (array_key_exists($namespace, $plugins)) {
    return $plugins[$namespace]['dataSourceConfig'];
  }
  // If the configuration is not being overridden, use
  // this one.
  else {
    return array(
      'config_file' => '/path/to/db_config.php'
    );
  }
}

/**
 * Required function. Defines the XML namespace that the elements
 * generated by this plugin belong to.
 *
 * @return
 *  An associative array containing the XML namespace prefix as a
 *  key and the namespace URI as its value.
 */
function getDataSourceNamespaces() {
  return array('xmlns:dcterms' => 'http://purl.org/dc/terms/');
}

/**
 * Required function. Defines the 'human-readable' web page for
 * an item.
 *
 * @param string $identifier
 *  The identifier portion of the request URI.
 *
 * @param object $app
 *  The Slim $app object.
 */
function getWebPage($identifier, $app) {
  list($namespace, $id) = explode(':', $identifier);
  if ($record = getRecord($namespace, $id)) {
    // The template we want to use is in the same directory
    // as this script.
    $app->config('templates.path', dirname(__FILE__));
    $app->render('pdotemplate.html', array('metadata' => $record));
  }
  else {
    $app->halt(404, 'Resource not found');
  }
}

/**
 * Generate the RDF XML for the item.
 *
 * Data source plugins are required to define this function
 * unless they are returning full RDF documents; in that case,
 * they must define the getResourceDataRaw() function instead.
 * See the 'static' plugin for an example.
 *
 * @param string $identifier
 *  The identifier portion of the request URI.
 *
 * @param object $xml
 *  The SimpleXML $xml object.
 *
 * @param object $app
 *  The Slim $app object.
 *
 * @return
 *  The SimpleXML $xml object.
 */
function getResourceData($identifier, $xml, $app) {
  list($namespace, $id) = explode(':', $identifier);
  if ($record = getRecord($namespace, $id)) {
    // Wrap each of the record's values in dc: namespaced XML elements.
    foreach ($record as $field => $value) {
      $xml->writeElementNS('dcterms', strtolower($field), NULL, $value);
    }
    return $xml;
  }
  else {
    $app->halt(404);
  }
}

/**
 * Function specific to this plugin.
 *
 * Returns an associative array with field label => values
 * for the record with the value of $id in the 'Identifier'
 * field in the database table. Returns FALSE if no record
 * is found.
 *
 * @param string $namespace
 *  The namespace portion of the request URI.
 *
 * @param string $id
 *  The ID portion of the request URI.
 */
function getRecord($namespace, $id) {
  $config = dataSourceConfig($namespace);
  require $config['config_file'];

  $dbh = new PDO('mysql:host=localhost;dbname=' . $database, $username, $password);
  $query = $dbh->prepare("SELECT * FROM data WHERE identifier = ?");
  $query->execute(array($id));
  $rows = $query->fetchAll(PDO::FETCH_ASSOC);

  if (count($rows)) {
    // We don't want the record's 'id' field in the output, since it's just the 
    // database table key.
    unset($rows[0]['id']);
    // An extra trick: since the same author wrote all the books in our database, 
    // we can add info here before returning the record. 
    $rows[0]['Author'] = 'Dick, Philip K.';
    return $rows[0];
  }
  else {
    return FALSE;
  }
}

