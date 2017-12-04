<?php
/**
 * Created by PhpStorm.
 * User: brayden
 * Date: 2017-10-14
 * Time: 10:47 PM
 *
 * @property Parsedown parsedown
 *
 */

class Helpme extends Application
{
    public function index() {
        $this->data['pagetitle'] = 'Help Wanted!';
        $this->data['content'] = '<p>help</p>';
        $this->render();
    }
}