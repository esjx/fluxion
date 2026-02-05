<?php

namespace Fluxion;

enum State
{

    case STATE_VIEW;
    case STATE_EDIT;
    case STATE_EXCEL;
    case STATE_FIELDS;
    case STATE_SYNC;
    case STATE_BEFORE_SAVE;
    case STATE_SAVE;
    case STATE_FILTER;
    case STATE_FILTER_PARAMS;
    case STATE_INLINE;
    case STATE_INLINE_SAVE;
    case STATE_TYPEAHEAD;

}
