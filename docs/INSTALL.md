# Installation guide

These is the install file for the Semantic Tasks extension. See also
the extension's page on mediawiki.org:

* https://www.mediawiki.org/wiki/Extension:Semantic_Tasks

## Requirements

Semantic Tasks requires:

* MediaWiki 1.23 or above
* PHP 5.3 or above
* Semantic MediaWiki 1.7 or above

## Download

You can get the code directly from Git. It can be obtained via

`git clone https://gerrit.wikimedia.org/r/mediawiki/extensions/SemanticTasks.git`

or use MediaWiki's extension distributor.

## Installation

Once you have downloaded the code, place the `SemanticTasks` directory within your MediaWiki
`extensions` directory. Then add the following code to your "LocalSettings.php" file:

```php
# Semantic Tasks
require_once "$IP/extensions/SemanticTasks/SemanticTasks.php";
```
