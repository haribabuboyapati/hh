<?php namespace Attendance\Database;

use Attendance\Database\Connection;

abstract class AbstractTable
{

    protected $table_name, $error;

    abstract public function create();

    public function destroy()
    {
        return $this->execute('DROP TABLE ' . $this->getTableName());
    }

    abstract public static function getInstance();

    public function select($cols, $where)
    {
        $query = "SELECT %s FROM %s %s";
        $col = "*";
        if (count($cols) > 0) {
            $col = implode(",", $cols);
        }
        $query = sprintf($query, $col, $this->getTableName(), $where);
        $stm = Connection::get()->prepare($query);
        if ($stm->execute()) {
            return $stm->fetchAll();
        }
        return [];
    }

    public function getTableName()
    {
        if ($this->table_name == null) {
            throw new \Exception('Table Name is null, make sure to set table name in constructor', 1);
        }
        return $this->table_name;
    }

    protected function execute($sql, $params = [])
    {
        $con = Connection::get();
        $pst = $con->prepare($sql);
        if (!$pst->execute($params) || $pst->rowCount() == 0) {
            $this->setError($pst->errorInfo());
            return false;
        }
        return true;
    }

    private function createInsertStatement($pv = [])
    {
        return sprintf('INSERT INTO %s (%s) VALUES (%s)', $this->getTableName(),
        implode(',', array_keys($pv)),
        str_repeat('?, ', count($pv) - 1) . '?');
    }

    private function createDeleteStatement($where = [])
    {
        $wheres = '';
        if (count($where) > 0) {
            $wheres .= 'WHERE ';
            foreach ($where as $key => $rel) {
                $wheres .= $key . ' ' . $this->getRelation($rel) . ' ? AND ';
            }
           //remove last AND
            $wheres = substr($wheres, 0, strlen($wheres) - 5);
        }
        return sprintf('DELETE FROM %s %s', $this->getTableName(), $wheres);
    }

  /**
   * Checks for the relation provided in the raw string and return matching one
   *
   * @param $text string A string containing the relation.
   *
   * @return string
   *
   */
    private function getRelation($text)
    {
        $text = ltrim($text);
        $match = substr($text, 0, 2);
        if ($match == '<=') {
            return '<=';
        } elseif ($match == '>=') {
            return '>=';
        }
        return '=';
    }

    public function insert($pv = [])
    {
        return $this->execute($this->createInsertStatement($pv), array_values($pv));
    }

    public function delete($wheres = [])
    {
        return $this->execute($this->createDeleteStatement($wheres),
         array_map(function ($text) {
              //remove the relations
            return preg_replace('/\s?<=\s?|\s?>=\s?|\s?=\s?/', '', $text, 1);
         }, array_values($wheres)));
    }

    public function getError()
    {
        return $this->error;
    }

    public function setError($err)
    {
        $this->error = $err;
    }
}
