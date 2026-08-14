'use strict';

const title = process.env.TIDO_TERMINAL_TITLE;

if (title) {
    process.title = title;
}
