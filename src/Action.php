<?php
namespace Fluxion;

enum Action: string
{

    # Gestão de dados
    case ADD = 'insert';
    case EDIT= 'edit';
    case UPDATE = 'update';
    case VIEW = 'view';
    case DELETE = 'delete';
    case DUPLICATE = 'duplicate';
    case MOVE = 'move';
    case ARCHIVE = 'archive';
    case RESTORE = 'restore';
    case INDEX = 'index';

    # Controle de acesso e segurança
    case BLOCK = 'block';
    case UNBLOCK = 'unblock';
    case ACTIVATE = 'activate';
    case DEACTIVATE = 'deactivate';
    case AUTHORIZE = 'authorize';
    case REVOKE = 'revoke';
    case VALIDATE = 'validate';
    case VERIFY = 'verify';
    case LOGIN = 'login';
    case LOGOUT = 'logout';
    case REGISTER = 'register';
    case RESET = 'reset';

    # Fluxo de trabalho e processos
    case SUBMIT = 'submit';
    case APPROVE = 'approve';
    case REJECT = 'reject';
    case CANCEL = 'cancel';
    case REVERT = 'revert';
    case SCHEDULE = 'schedule';
    case RESCHEDULE = 'reschedule';
    case PRIORITIZE = 'prioritize';
    case CATEGORIZE = 'categorize';
    case CONFIRM = 'confirm';
    case PROVISION = 'provision';
    case DEPROVISION = 'deprovision';
    case START = 'start';
    case STOP = 'stop';
    case PAUSE = 'pause';
    case RESUME = 'resume';
    case RESTART = 'restart';
    case FINISH = 'finish';
    case OPEN = 'open';
    case REOPEN = 'reopen';
    case CLOSE = 'close';

    # Integração e comunicação
    case PRINT = 'print';
    case EXPORT = 'export';
    case IMPORT = 'import';
    case LINK = 'link';
    case UNLINK = 'unlink';
    case NOTIFY = 'notify';
    case PUBLISH = 'publish';
    case UNPUBLISH = 'unpublish';
    case SIGN = 'sign';
    case LOG = 'log';
    case DOWNLOAD = 'download';
    case UPLOAD = 'upload';
    
}
