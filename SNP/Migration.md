#### 1.3.6

``php bin/console app:sync:eudonet 1 company "has_dashboard = 1"``

#### 1.4.0

``php bin/console app:sync:eudonet 1 formation``


#### 1.4.2

``php bin/console app:sync:eudonet 1 company "sales != ''"``

``php bin/console app:sync:eudonet 1 formation "hours_discrimination != ''"``

#### 1.4.14

``php bin/console app:sync:eudonet 1 formation_course``

#### 1.4.15

``php bin/console app:sync:eudonet 1 formation``

#### 1.5.0

``UPDATE address set email=null, email_hash=null where 1;``

``php bin/console app:import address email 18 address.csv``

``php bin/console app:import address summary 21 address.csv``

``php bin/console app:sync:eudonet 1 agreement``

`` php bin/console app:sync:eudonet 1 instructor``

#### 1.7.0

``php bin/console app:sync:eudonet 1 formation_course``

``php bin/console app:sync:eudonet 1 agreement``

``php bin/console app:sync:eudonet 1 formation_price``

``php bin/console app:sync:eudonet 1 formation_participant``

#### 1.8.0

``php bin/console app:sync:eudonet 1 company_representative``
``php bin/console app:sync:cms 1``