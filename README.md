WPModel
=======

```php
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
