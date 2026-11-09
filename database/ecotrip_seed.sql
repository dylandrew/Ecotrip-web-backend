-- Demo data for a fresh/empty database only. Do not import into a populated database.
USE ecotrip_lavalust_db;
INSERT INTO users (id,name,email,password_hash,phone,role) VALUES
 (1,'Demo Traveler','demo@ecotrip.test','$2y$10$G695.ER.v2AgFzQOD4eejuM4YbO.qa/0GV55BBoLvt.QgbA5VbCD.','+639170000000','traveler');

INSERT INTO stays (id,name,location,eco_rating,price_per_night,certification,description) VALUES
 (1,'Pine Eco Lodge','Baguio',4,1800.00,'Green Key','Solar-heated rooms, rainwater reuse, locally sourced breakfast.'),
 (2,'Bamboo Hostel','Siargao',5,950.00,'EarthCheck','Built from bamboo, plastic-free, bike rentals on site.'),
 (3,'Sagada Farm Stay','Sagada',4,1200.00,'Local Eco Seal','Working organic farm with compost toilets and farm-to-table meals.'),
 (4,'Mangrove Cabins','Palawan',5,2400.00,'Green Globe','Off-grid cabins beside a protected mangrove reserve.'),
 (5,'City Green Inn','Cebu City',3,1100.00,NULL,'Energy-efficient inn near the bus terminal.'),
 (6,'City Comfort Hotel','Cebu City',0,1800.00,NULL,'A standard hotel option in Cebu City for travelers who prefer a conventional place to stay.');

INSERT INTO transport_options (id,mode,origin,destination,distance_km,co2_kg_per_person,cost) VALUES
 (1,'bus','Manila','Baguio',250.00,9.10,650.00),
 (2,'bus','Manila','Sagada',420.00,15.30,1100.00),
 (3,'ferry','Cebu City','Siargao',300.00,18.00,1500.00),
 (4,'bike','Baguio','Burnham Park',4.50,0.00,0.00),
 (5,'train','Manila','Calamba',54.00,1.60,60.00),
 (6,'carpool','Manila','Baguio',250.00,21.00,900.00),
 (7,'walk','Siargao Town','Tuason Point',2.00,0.00,0.00);

INSERT INTO trips (id,user_id,title,destination,start_date,end_date,budget,travelers,total_co2_kg,status) VALUES
 (1,1,'Baguio Eco Trip','Baguio','2026-12-10','2026-12-13',8000.00,2,14.20,'planned'),
 (2,1,'Siargao Bike Trip','Siargao','2027-01-20','2027-01-25',15000.00,1,18.00,'draft'),
 (3,1,'Sagada Weekend','Sagada','2026-09-04','2026-09-06',6000.00,3,15.30,'completed');

INSERT INTO itinerary_items (trip_id,day_number,item_type,stay_id,transport_id,description,start_time,est_cost,est_co2_kg) VALUES
 (1,1,'transport',NULL,1,'Bus: Manila to Baguio','08:00:00',650.00,9.10),
 (1,1,'stay',1,NULL,'Check-in: Pine Eco Lodge','15:00:00',1800.00,0.00),
 (1,2,'transport',NULL,4,'Bike path: Burnham Park loop','09:00:00',0.00,0.00),
 (2,1,'transport',NULL,3,'Ferry: Cebu City to Siargao','07:00:00',1500.00,18.00),
 (2,1,'stay',2,NULL,'Check-in: Bamboo Hostel','16:00:00',950.00,0.00),
 (3,1,'transport',NULL,2,'Bus: Manila to Sagada','20:00:00',1100.00,15.30),
 (3,2,'stay',3,NULL,'Farm stay: Sagada Farm Stay','14:00:00',1200.00,0.00);
