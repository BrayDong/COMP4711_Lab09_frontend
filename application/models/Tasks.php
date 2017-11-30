<?php
/**
 * Modified to use REST client to get port data from our server.
 */
define('REST_SERVER', 'http://backend.local');  // the REST server host
define('REST_PORT', $_SERVER['SERVER_PORT']);   // the port you are running the server on

/**
 * Created by PhpStorm.
 * User: kimdongwon
 * Date: 2017-10-12
 * Time: 1:50 PM
 */

// return -1, 0, or 1 of $a's category name is earlier, equal to, or later than $b's
function orderByCategory($a, $b) {
    if ($a->group < $b->group)
        return -1;
    elseif ($a->group > $b->group)
        return 1;
    else
        return 0;
}


class Tasks extends XML_Model {

    private $app;
    public function __construct()
    {
        $this -> app = new App();
        parent::__construct(APPPATH . '../data/tasks.xml', 'id');
        //print_r($)
    }

    public function getCategorizedTasks()
    {
        // extract the undone tasks
        foreach ($this->all() as $task)
        {
            if ($task->status != 2)
                $undone[] = $task;
        }

        // substitute the category name, for sorting
        foreach ($undone as $task)
            $task->group = $this->app->group($task->group);

        // order them by category
        usort($undone, "orderByCategory");

        // convert the array of task objects into an array of associative objects
        foreach ($undone as $task)
            $converted[] = (array) $task;

        return $converted;
    }

    // provide form validation rules
    public function rules()
    {
        $config = array(
            ['field' => 'task', 'label' => 'TODO task', 'rules' => 'alpha_numeric_spaces|max_length[64]'],
            ['field' => 'priority', 'label' => 'Periority', 'rules' => 'integer|less_than[4]'],
            ['field' => 'size', 'label' => 'Task size', 'rules' => 'integer|less_than[4]'],
            ['field' => 'group', 'label' => 'Task group', 'rules' => 'integer|less_than[5]'],
        );
        return $config;
    }

    public function load(){

        $this->rest->initialize(array('server' => REST_SERVER));
        $this->rest->option(CURLOPT_PORT, REST_PORT);
        $this->_data =  $this->rest->get('/job');

        $this -> reindex();
    }

//    protected function store(){
//
//    }
//
//    function get($key, $key2 = null){
//      $this->rest->initialize(array('server' => REST_SERVER));
//      $this->rest->option(CURLOPT_PORT, REST_PORT);
//      return $this->rest->get('/job/' . $key);
//    }
//
//    // Delete a record from the DB
//    function delete($key, $key2 = null)
//    {
//        $this->rest->initialize(array('server' => REST_SERVER));
//        $this->rest->option(CURLOPT_PORT, REST_PORT);
//        $this->rest->delete('/job/' . $key);
//        $this->load(); // because the "database" might have changed
//    }
//
//
//    function update($record)
//    {
//          $this->rest->initialize(array('server' => REST_SERVER));
            //$this->rest->option(CURLOPT_PORT, REST_PORT);
            //$key = $record->{$this->_keyfield};
            //$retrieved = $this->rest->put('/job/' . $key, $record);
            //$this->load(); // because the "database" might have changed
//    }
//
//    // Add a record to the collection
//    function add($record)
//    {
//        $this->rest->initialize(array('server' => REST_SERVER));
        //$this->rest->option(CURLOPT_PORT, REST_PORT);
        //$key = $record->{$this->_keyfield};
        //$retrieved = $this->rest->post('/job/' . $key, $record);
        //$this->load(); // because the "database" might have changed
//    }
}