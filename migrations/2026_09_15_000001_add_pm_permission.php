<?php

use Flarum\Database\Migration;
use Flarum\Group\Group;

return Migration::addPermissions([
    'pm.start' => Group::MEMBER_ID,
]);
