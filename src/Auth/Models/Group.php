<?php
namespace Fluxion\Auth\Models;

use Fluxion\Sql;
use Fluxion\Util;
use stdClass;
use Fluxion\ModelOld;
use Fluxion\Query;

class Group extends ModelOld
{

    const USUARIO_COMUM = 10;
    const ATACADO = 17;
    const ATACADO_2 = 21;
    const VAREJO = 16;
    const VAREJO_2 = 23;
    const GETAT = 69;
    const ESPECIALIZADA = 11;
    const ESPECIALIZADA_2 = 22;
    const CENTRALIZADORA = 12;
    const CENTRALIZADORA_GESTAO = 26;
    const CENTRALIZADORA_RENEG = 74;
    const CESNE = 14;
    const HOMOLOGACAO_2 = 18;
    const SUAGR = 5;
    const FULL_TIME = 85;
    const GEGAD = 68;
    const GEFOA = 1;
    const GEGRO_SUPORTE = 3;
    const GEGRO_TECNICA = 6;
    const HOMOLOGACAO = 28;
    const RCN_MASTER = 30;
    const SUPERINTENDENTES = 37;
    const AGRO_ATENDE_PLUS = 45;
    const CEMOB = 39;
    const GEACV = 54;
    const GEGRO05 = 59;
    const LISTA_COOPERADOS_PLUS = 36;
    const DITCO = 55;
    const PAINEL_SEGURO = 57;

    const ORCAMENTO_MASTER = 46;
    const ORCAMENTO_DISTRIBUIR = 47;

    const MINUTAS_MASTER = 52;
    const GESTAO_PROJETOS = 53;
    const SEGURO_MASTER = 67;
    const GEGRO_EXCECOES = 71;
    const GEGRO_GESTAO = 7;
    const GEGRO_PRODUTOS = 24;
    const CECOQVT = 49;
    const PILOTO_RCN = 58;
    const GT_ADIMPLENCIA = 72;
    const FUNCAO_GERENCIAL = 73;
    const REENVIO_SICOR = 75;
    const RGI_BETA = 70;
    const GESTORES_SUAGR = 86;

    protected $_verbose_name = 'Grupo';

    protected $_ipp = 24;

    protected $_table = 'auth._group';

    protected $_order = [
        ['name', 'ASC'],
    ];

    public $name = [
        'type' => 'string',
        'label' => 'Nome',
        'required' => true,
        'field_size' => 8,
    ];

    public $description = [
        'type' => 'string',
        'label' => 'Descrição',
        'required' => false,
    ];

    public $deadline = [
        'type' => 'string',
        'label' => 'Prazo Padrão',
        'choices' => [
            '+1 day' => '1 Dia',
            '+2 days' => '2 Dias',
            '+3 days' => '3 Dias',
            '+1 week' => '1 Semana',
            '+2 weeks' => '2 Semanas',
            '+3 weeks' => '3 Semanas',
            '+1 month' => '1 Mês',
            '+2 months' => '2 Meses',
            '+3 months' => '3 Meses',
            '+6 months' => '6 Meses',
            '+1 year' => '1 Ano',
        ],
        'help' => 'Deixar sem preenchimento para prazo indeterminado',
        'required' => false,
    ];

    public $heritages = [
        'type' => 'integer',
        'label' => 'Heranças',
        'many_to_many' => __CLASS__,
        'protected' => true,
    ];

    public $under = [
        'type' => 'integer',
        'label' => 'Grupos',
        'i_many_to_many' => __CLASS__,
        'protected' => true,
    ];

    public $expires = [
        'type' => 'date',
        'label' => 'Expira em',
        'required' => false,
        'protected' => true,
    ];

    public function __toString()
    {

        return $this->name;

    }

    public function subtitle(): string
    {

        $config = $this->_config;
        $auth = $this->_auth;

        $total = UserGroup::filter('_group', $this->id)
                ->count('DISTINCT _user')
                ->firstOrNew($config, $auth)->total * 1;

        switch ($total) {

            case 0:

                return 'Nenhum Usuário';

            case 1:

                return '1 Usuário';

            default:

                $total = Util::formatNumber($total, false, 0);

                return $total . ' Usuários';

        }

    }

    protected $_default_order = 2;

    public function orders(): array
    {

        $orders = parent::orders();

        $orders[] = [
            'id' => 2,
            'label' => 'Nome',
        ];

        return $orders;
    }

    public function order(Query $query, $order): Query
    {

        $this->_default_order = $order;

        if ($order == 2) {

            $query->orderBy('name', ASC);

        } else {

            $query = parent::order($query, $order);

        }

        return $query;

    }

    public function extras(): array
    {

        $config = $this->_config;
        $auth = $this->_auth;

        $users = [];

        foreach (UserGroup::filter('_group', $this->id)
                     ->orderBy('_update', DESC)
                     ->groupBy('_user')
                     ->max('_update', '_update')
                     ->limit(4)
                     ->xselect($config, $auth) as $user) {

            $users[] = $auth->userData($user->_user);

        }

        return [
            'users' => $users,
        ];

    }

    public function actions(): array
    {

        //$config = $this->_config;
        $auth = $this->_auth;

        $actions = [];

        if ($auth->getUser()->super_user) {

            $actions[] = [
                'id' => self::ACTION_LIMPAR,
                'type' => 'action',
                'label' => 'Limpar',
                'disabled' => false,
                'confirm' => 'Deseja remover todos os integrantes do grupo?',
            ];

            $actions[] = [
                'id' => self::ACTION_APAGAR,
                'type' => 'action',
                'label' => 'Apagar',
                'disabled' => false,
                'confirm' => 'Deseja apagar o grupo?',
            ];

        }

        return $actions;

    }

    public function executeAction($action): bool
    {

        $config = $this->_config;
        $auth = $this->_auth;

        if (!$auth->getUser()->super_user) {
            return false;
        }

        if ($action == self::ACTION_LIMPAR) {

            UserGroup::filter('_group', $this->id)->delete($config, $auth);

        } else {

            return parent::executeAction($action);

        }

        return true;

    }

    public function filterItens(Query $query, stdClass $itens): Query
    {

        $config = $this->_config;
        $auth = $this->_auth;

        if (!$this->hasPermission('special')) {

            $query = $query->filter('id', UserGroup
                ::filter('_user', $auth->getUser()->login)
                ->filter('_type', UserGroup::GESTOR)
                ->only('_group')->toArray($config, $auth));

        }

        return parent::filterItens($query, $itens);

    }

    public function hasSearch(): bool
    {
        return true;
    }

    public function search(Query $query, $search): Query
    {

        return $query->filter(Sql::_or([
            Sql::filter('name__like', $search . '%'),
            Sql::filter('description__like', $search . '%'),
            Sql::filter('id', UserGroup::filter('_user', $search)->only('_group')),
        ]));

    }

}
