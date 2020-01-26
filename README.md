# tongs metadata plugin

Permalinks plugin for [Tongs](https://github.com/datashaman/tongs) static site generator.

## setup

If you add the following config to your _tongs.json_ file:

    {
      "plugins": {
        "permalinks": {
          "pattern": ":date-:title"
        }
      }
    }

## source

This plugin is heavily based on [metalsmith-permalinks](https://github.com/segmentio/metalsmith-permalinks/).
