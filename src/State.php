<?php

namespace Fluxion;

enum State
{

    case LIST;
    case LIST_CHOICE;
    case VIEW;
    case EDIT;
    case DOWNLOAD;
    case FIELDS;
    case SYNC;
    case BEFORE_SAVE;
    case SAVE;
    case FILTER;
    case FILTER_PARAMS;
    case INLINE;
    case INLINE_SAVE;
    case INLINE_VIEW;
    case TYPEAHEAD;

}
