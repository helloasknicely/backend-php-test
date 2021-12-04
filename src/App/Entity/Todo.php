<?php
namespace App\Entity;

class Todo
{
    public const STATUSES = array(
    	'PROGRESS' => 'In Progress',
    	'COMPLETE' => 'Completed'
    );

    public const PAGESIZE = 5;
}