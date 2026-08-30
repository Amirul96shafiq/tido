'use strict';

const util = require('node:util');

/**
 * Evolution logs webhook payloads with console.log(object). Node's default
 * inspect depth is 2, so messages.upsert bodies show as [Object] under
 * concurrently (piped stdout). Deepen inspect without dumping entire base64 media.
 */
util.inspect.defaultOptions.depth = 10;
util.inspect.defaultOptions.maxArrayLength = 100;
util.inspect.defaultOptions.maxStringLength = 2000;
util.inspect.defaultOptions.breakLength = 120;
