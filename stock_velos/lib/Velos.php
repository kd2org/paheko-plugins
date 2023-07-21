<?php

namespace Paheko\Plugin\Stock_Velos;

use Paheko\DB;
use Paheko\Membres;
use Paheko\UserException;
use Paheko\Utils;
use Paheko\DynamicList;
use Paheko\Users\Session;

use KD2\DB\EntityManager;
use KD2\Graphics\SVG\Bar;
use KD2\Graphics\SVG\Bar_Data_Set;

class Velos
{
    const A_DEMONTER = -1;

    protected $db = null;

    protected $max_etiquettes = 1000;

    protected $columns_order = array(
        'id',
        'etiquette',
        'date_entree',
        'date_sortie',
        'raison_sortie',
        'couleur',
        'modele',
        'type',
        'roues',
        'genre',
        'prix',
        'statut',
    );

    protected $types = array(
        'Course',
        'Mi-course',
        'Mini',
        'Pliant',
        'Ville',
        'VTC',
        'VTT',
        'BMX',
        'Autre',
    );

    protected $genres = array(
        'Homme',
        'Femme',
        'Mixte',
        'Enfant/Homme',
        'Enfant/Femme',
        'Enfant/Mixte',
    );

    protected $tailles = array(
        '700C',
        '650B',
        '26"',
        '24"',
        '20"',
        'Autre',
    );

    protected $sources = array(
        'Achat',
        'Don',
        'Rachat',
        'Récupération',
        'Partenariat',
    );

    protected $raisons_sortie = array(
        'Démonté',
        'Vendu',
        'Vendu en bourse',
        'Jeté',
    );

    /**
     * Genres de vélos
     */

    public function listGenres()
    {
        return array_combine($this->genres, $this->genres);
    }
    /**
     * Sources de vélo
     */

    public function listSources()
    {
        return array_combine($this->sources, $this->sources);
    }

    /**
     * Types de vélos
     */

    public function listTypes()
    {
        return array_combine($this->types, $this->types);
    }

    /**
     * Raisons de sortie
     */

    public function listRaisonsSortie()
    {
        return array_combine($this->raisons_sortie, $this->raisons_sortie);
    }

    /**
     * Tailles des vélos
     */

    public function listTailles()
    {
        return array_combine($this->tailles, $this->tailles);
    }

    public function addVelosDemontes(int $nb, string $source, string $source_details)
    {
        $db = DB::getInstance();

        $data = [
            'source' => trim($source),
            'source_details' => trim($source_details) ?: null,
            'date_entree' => gmdate('Y-m-d'),
            'etat_entree' => 'À démonter',
            'date_sortie' => gmdate('Y-m-d'),
            'raison_sortie' => 'Démonté',
            'details_sortie' => 'Saisie rapide de plusieurs vélos démontés',
        ];

        for ($i = 0; $i < $nb; $i++) {
            $db->insert('plugin_stock_velos', $data);
        }

        return true;
    }

    static public function get(int $id): ?Velo
    {
        return EntityManager::findOneById(Velo::class, $id);
    }

    public function listVelosStock()
    {
        $columns = [
            'id' => [
                'label' => 'Num.',
            ],
            'etiquette' => [
                'label' => 'Étiq.',
            ],
            'type' => [
                'label' => 'Type',
            ],
            'roues' => [
                'label' => 'Roues'
            ],
            'genre' => [
                'label' => 'Genre'
            ],
            'modele' => [
                'label' => 'Modèle'
            ],
            'couleur' => [
                'label' => 'Couleur'
            ],
            'prix' => [
                'label' => 'Prix'
            ],
            'date_entree' => [
                'label' => 'Entrée'
            ],
        ];


        $tables = 'plugin_stock_velos';
        $conditions = 'date_sortie IS NULL';

        $list = new DynamicList($columns, $tables, $conditions);
        $list->orderBy('etiquette', false);
        $list->setCount('COUNT(*)');
        return $list;
    }

    public function listVelosHistorique()
    {
        $columns = [
            'id' => [
                'label' => 'Num.',
            ],
            'type' => [
                'label' => 'Type',
            ],
            'roues' => [
                'label' => 'Roues'
            ],
            'genre' => [
                'label' => 'Genre'
            ],
            'modele' => [
                'label' => 'Modèle'
            ],
            'couleur' => [
                'label' => 'Couleur'
            ],
            'prix' => [
                'label' => 'Prix'
            ],
            'date_sortie' => [
                'label' => 'Sortie'
            ],
            'raison_sortie' => [
                'label' => 'Raison'
            ],
        ];

        $tables = 'plugin_stock_velos';
        $conditions = 'date_sortie IS NOT NULL';

        $list = new DynamicList($columns, $tables, $conditions);
        $list->orderBy('date_sortie', true);
        $list->setCount('COUNT(*)');
        return $list;
    }

    public function countVelosStock()
    {
        return DB::getInstance()->firstColumn('SELECT COUNT(*) FROM plugin_stock_velos WHERE date_sortie IS NULL;');
    }

    public function countVelosHistorique()
    {
        return DB::getInstance()->firstColumn('SELECT COUNT(*) FROM plugin_stock_velos WHERE date_sortie IS NOT NULL;');
    }

    public function listEtiquettes()
    {
        $etiquettes = array();

        for ($i = 1; $i <= $this->max_etiquettes; $i++)
        {
            $etiquettes[$i] = false;
        }

        $db = DB::getInstance();

        foreach ($db->iterate('SELECT etiquette, prix FROM plugin_stock_velos WHERE date_sortie IS NULL;') as $row)
        {
            $etiquettes[$row->etiquette] = (float) $row->prix;
        }

        return $etiquettes;
    }

    public function getValeurStock()
    {
        return DB::getInstance()->firstColumn('SELECT SUM(prix) FROM plugin_stock_velos WHERE date_sortie IS NULL AND prix > 0;');
    }

    public function getEtiquetteLibre()
    {
        $etiquettes = $this->listEtiquettes();

        foreach ($etiquettes as $num=>$stock)
        {
            if ($stock === false)
                return $num;
        }

        return false;
    }

    public function getIdFromEtiquette($etiquette)
    {
        return DB::getInstance()->firstColumn('SELECT id FROM plugin_stock_velos WHERE date_sortie IS NULL AND etiquette = ?;', (int)$etiquette);
    }

    public function search($field, $search)
    {
        $query = 'SELECT * FROM plugin_stock_velos WHERE ';
        $db = DB::getInstance();

        if ($field == 'etiquette')
        {
            $query .= $field . ' = \'' . $db->escapeString($search) . '\'';
        }
        else
        {
            $query .= $field . ' LIKE \'%' . $db->escapeString($search) . '%\'';
        }

        $query .= ' ORDER BY id DESC;';

        return $db->get($query);
    }

    public function searchSQL($fields, $query)
    {
        $fields = trim($fields);

        if ($fields === '')
        {
            $fields = '*';
        }

        $query = 'SELECT ' . $fields . ' FROM plugin_stock_velos ' . $query;
        
        $db = DB::getInstance();

        try {
            $statement = $db->prepare($query);
        }
        catch (\Exception $e)
        {
            throw new UserException('Erreur de requête: ' . $e->getMessage());
        }

        if (!$statement->readOnly())
        {
            throw new UserException('Requête invalide : ne doit effectuer que des lectures de données.');
        }

        return $db->get($query);
    }

    public function getSchemaSQL()
    {
        $schema = DB::getInstance()->firstColumn('SELECT sql FROM sqlite_master 
            WHERE type = "table" AND name="plugin_stock_velos";');

        $schema = str_replace('CREATE TABLE plugin_stock_velos', 'CREATE TABLE velos', $schema);
        return $schema;
    }


    public function listVelosToSell()
    {
        return DB::getInstance()->get('SELECT * FROM plugin_stock_velos
            WHERE date_sortie IS NULL AND prix > 0 ORDER BY etiquette;');
    }

    public function statsByMonth()
    {
        $sql = '
            SELECT
                strftime(\'%Y-%m\', date_sortie) AS month,
                \'Sortie\' AS type,
                raison_sortie AS details,
                COUNT(*) AS nb
                FROM plugin_stock_velos
                WHERE raison_sortie IS NOT NULL
                GROUP BY strftime(\'%m/%Y\', date_sortie), raison_sortie
            UNION ALL
            SELECT
                strftime(\'%Y-%m\', date_entree) AS month,
                \'Entrée\' AS type,
                source AS details,
                COUNT(*) AS nb
                FROM plugin_stock_velos
                GROUP BY strftime(\'%m/%Y\', date_entree), source
            ORDER BY month DESC, type, details;
        ';
        return DB::getInstance()->get($sql);
    }

    public function statsByYear()
    {
        $sql = '
            SELECT
                strftime(\'%Y\', date_sortie) AS year,
                \'Sortie\' AS type,
                raison_sortie AS details,
                COUNT(*) AS nb
                FROM plugin_stock_velos
                WHERE raison_sortie IS NOT NULL
                GROUP BY strftime(\'%Y\', date_sortie), raison_sortie
            UNION ALL
            SELECT
                strftime(\'%Y\', date_entree) AS year,
                \'Entrée\' AS type,
                source AS details,
                COUNT(*) AS nb
                FROM plugin_stock_velos
                GROUP BY strftime(\'%Y\', date_entree), source
            ORDER BY year DESC, type, details;
        ';
        return DB::getInstance()->get($sql);
    }

    static public function graphStatsPerYear(): string
    {
        $sql = '
           SELECT
                strftime(\'%Y\', date_sortie) AS year,
                \'Sortie\' AS type,
                COUNT(*) AS nb
                FROM plugin_stock_velos
                WHERE raison_sortie IS NOT NULL
                GROUP BY strftime(\'%Y\', date_sortie)
            UNION ALL
            SELECT
                strftime(\'%Y\', date_entree) AS year,
                \'Entrée\' AS type,
                COUNT(*) AS nb
                FROM plugin_stock_velos
                GROUP BY strftime(\'%Y\', date_entree)
            ORDER BY year, type;';

        $data = DB::getInstance()->getAssocMulti($sql);
        return self::barGraph(null, $data);
    }

    static public function graphStatsPerExit(): string
    {
        $sql = '
           SELECT
                strftime(\'%Y\', date_sortie) AS year,
                raison_sortie,
                COUNT(*) AS nb
                FROM plugin_stock_velos
                WHERE raison_sortie IS NOT NULL
                GROUP BY strftime(\'%Y\', date_sortie), raison_sortie ORDER BY year, raison_sortie;
        ';

        $data = DB::getInstance()->getAssocMulti($sql);
        return self::barGraph(null, $data);
    }

    static public function graphStatsPerEntry(): string
    {
        $sql = '
           SELECT
                strftime(\'%Y\', date_entree) AS year,
                source,
                COUNT(*) AS nb
                FROM plugin_stock_velos
                WHERE raison_sortie IS NOT NULL
                GROUP BY strftime(\'%Y\', date_entree), source ORDER BY year, source;
        ';

        $data = DB::getInstance()->getAssocMulti($sql);
        return self::barGraph(null, $data);
    }

    static public function barGraph(?string $title, array $data): string
    {
        $bar = new Bar(1000, 400);
        $bar->setTitle($title);
        $current_group = null;
        $set = null;
        $sum = 0;

        $color = function (string $str): string {
            return sprintf('#%s', substr(md5($str), 0, 6));
        };

        foreach ($data as $group_label => $group) {
            $set = new Bar_Data_Set($group_label);
            $sum = 0;

            foreach ($group as $label => $value) {
                $set->add($value, $label, $color($label));
                $sum += $value;
            }

            $bar->add($set);
        }

        return $bar->output();
    }
}
