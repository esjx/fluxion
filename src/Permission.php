<?php
namespace Fluxion;

enum Permission
{

    case LIST;
    case LIST_UNDER;
    case LIST_ALL;
    case VIEW;
    case INSERT;
    case UPDATE;
    case DELETE;
    case DOWNLOAD;

}
