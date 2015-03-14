## Introduction ##

BatchEdit is an admin plugin that allows you to use regular expressions to search and replace text on DokuWiki pages. As it works with raw DokuWiki text, the plugin can be also used to modify markup of the pages. This can be very helpful when there are multiple pages with similar markup. For example, you can update pages created from a [namespace template](http://www.dokuwiki.org/namespace_templates) if you decide to change the template.


## Interface ##

After installation BatchEdit shows up on the _Administration_ page.

When started, the plugin displays a form with four edit fields:
  * _Namespace_ -- allows you to select a top level [namespace](http://www.dokuwiki.org/namespaces) that contains pages to search in. All the namespaces below the selected one will be included in the search as well. Standard name resolution of the namespaces applies here (e.g. "." stands for the current namespace, etc.). If the namespace is not provided, BatchEdit will search through the entire wiki.
  * _Regular expression_ -- fully qualified regular expression including the modifiers. BatchEdit uses [PHP PCRE extension](http://www.php.net/manual/en/book.pcre.php) to do the matching.
  * _Replacement_ -- the replacement pattern. For the syntax see [preg\_replace()](http://www.php.net/manual/en/function.preg-replace.php) documentation.
  * _Summary_ -- summary of the replacement. This field has the same purpose as the _Summary_ field of the DokuWiki text editor.

Below the edit fields there are two buttons:
  * _Preview_ -- shows the search results with corresponding replacements but does not do the replacement itself.
  * _Apply_ -- replaces selected (see below) matches. If there are no selected matches it acts identical to the _Preview_ button.

BatchEdit displays every match of the search results in a separate box. On top of the box the plugin shows a check box with the page name and a character offset where the match occurred. The check box is used to select matches for the replacement. To the right from the page name there are two icons: the first one links to the page itself and the second one to the editor. The rest of the box is split in two parts: matched text with some context on the left side; and the same fragment with applied replacement on the right. Both the matched text and the replacement are highlighted.

After the replacement, the matches show up with no check box in the caption and replaced text is highlighted with green.


## Installation ##

You can use [Plugin Manager](http://www.dokuwiki.org/plugin:plugin) to install the [current release](http://dwp-forge.googlecode.com/files/batchedit-2008-10-27.zip) or download it manually and unpack to plugins directory.


## Technical details ##

### Performance ###

BatchEdit does not rely on any caching to do the search, so every time _Preview_ or _Apply_ button is clicked the DokuWiki server reads all the pages from a hard drive. To reduce the server load and search time use _Namespace_ field that limits the search scope.


### Page locking ###

While BatchEdit locks pages during replacement, there is still a small possibility for the data corruption. The replacement is performed in two stages:
  1. BatchEdit searches for the regular expression matches in all the pages. For every match the plugin records character offset in the page where the match occurs.
  1. On the second stage BatchEdit compares matches found during the first pass with a list of matches selected for replacement. For the matches found in both lists BatchEdit locks the page and performs the replacement using the offset.

If the page is modified between these two stages, BatchEdit will apply the replacement to random data in the updated page. Though the chances of such corruption are rather small, the administrators should take care to minimize DokuWiki activity when Batchedit is used.


### Page lookup ###

BatchEdit uses DokuWiki page index to get the list of existing pages instead of going through the data directories. If the index is incomplete the plugin will not see some pages. This also applies to the "special" pages, for example, namespace templates.