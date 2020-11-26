# Semantic Tasks Release Notes

These are the release notes for the **Semantic Tasks** (a.k.a. ST) extension. See also
the extension's page on mediawiki.org:

* https://www.mediawiki.org/wiki/Extension:Semantic_Tasks

## Version 2.0.1

Released on 2020-11-26

* Fixes `$wgSemanticTasksNotifyIfUnassigned` being set incorrectly
* Updated translations (by translatewiki.net community)

## Version 2.0.0

Released on 2019-11-30

* Minimum requirement for
  * PHP changed to version 5.6 and later
  * MediaWiki changed to version 1.31 and later
  * Semantic MediaWiki changed to version 3.0 and later
* Added support for extension registration via "extension.json"  
  → Now you have to use `wfLoadExtension( 'SemanticTasks' );` in the "LocalSettings.php" file to invoke the extension
* Introduced custom property mapping
* Reworked and improved extension documentation
* Updated translations (by translatewiki.net community)

## Version 1.7.0

Released on 2017-08-01

* Removed I18n php shim
* Dropped support for MediaWiki 1.22.x and earlier.
* Updated translations (by translatewiki.net community)

## Version 1.6.0

Released on 2015-4-09

* Made compatible with MediaWiki 1.24.x and later.
* Removed deprecated code.
* Added option to notify users when unassigned from a task.
* Updated translations (by translatewiki.net community)

## Version 1.5.0

Released on 2014-04-01

* Migrated to JSON i18n
* Updated translations (by translatewiki.net community)

## Version 1.4.1

Released on 2011-12-06

* Added support for Semantic MediaWiki 1.7.x and later.
* Dropped support for MediaWiki 1.15.x and earlier.
* Updated translations (by translatewiki.net community)

## Version 1.4.0

Released in June 2010

* Fixed bug that caused notifications to fail in non-main namespaces.
* Updated translations (by translatewiki.net community)
