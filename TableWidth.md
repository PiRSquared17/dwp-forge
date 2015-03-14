## Introduction ##

The plugin allows to specify width for a DokuWiki table and its columns. This is a simple extension to the original table syntax, not a [full-fledged rework of the syntax](http://www.dokuwiki.org/plugin:exttab1).


## Syntax ##

The width has to be specified at the start of a line before the table. The first value is width of the table, the rest is for columns. If you want to omit some value use a dash instead. The widths can be specified in any CSS units:

```
 |< 100% 50px - 5em 10em 10% >|
 ^ A  ^  B  ^  C  ^  D  ^  E  ^
 | 1  |  2  |  3  |  4  |  5  |
```

If there are more columns in the table than there are values in the width specification, the width will be applied to the columns from left to right.

```
 |< 50em 20% >|
 ^ 20%              ^ 80%                          ^
 | Specified width  | The rest of the table width  |
```


## Installation ##

You can use [Plugin Manager](http://www.dokuwiki.org/plugin:plugin) to install the [current release](http://dwp-forge.googlecode.com/files/tablewidth-2009-02-14.zip) or download it manually and unpack to plugins directory.