WPModel
=======

<h3>Getting started example:</h3>

```php
<?php
require 'wpmodel.php';

class Person extends WPModel {

}

Person::insert(array(
  'name' => 'John'
));

$people = Person::find();

for ( $people as $person ) {
  echo 'Hello, ' . $person->name;
}
```
