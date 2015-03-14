## Introduction ##

Entity is a simple syntax plugin that allows you to insert [HTML character entities](http://www.w3.org/TR/REC-html40/sgml/entities.html) on a DokuWiki page.


## Syntax ##

To insert an entity just duplicate ampersand and semicolon characters:

```
 a &&le;; b

 90&&deg;;
```

This code will render as:

> a ≤ b

> 90°


## Installation ##

You can use [Plugin Manager](http://www.dokuwiki.org/plugin:plugin) to install the [current release](http://dwp-forge.googlecode.com/files/entity-2007-10-26.zip) or download it manually and unpack to plugins directory.