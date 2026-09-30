<?php
/**
 * NFL 2025 seed data — 32 teams and their star rosters (ported from nfl_data.py).
 * Ratings scale 60-99 Madden-style.
 */

final class NflData {
    public const TEAMS = [
        // AFC East
        ['id' => 'BUF', 'name' => 'Bills',    'city' => 'Buffalo',      'conf' => 'AFC', 'div' => 'East',  'primary' => '#00338D', 'secondary' => '#C60C30', 'off' => 89, 'def' => 84, 'st' => 82, 'coach' => 'Sean McDermott'],
        ['id' => 'MIA', 'name' => 'Dolphins', 'city' => 'Miami',        'conf' => 'AFC', 'div' => 'East',  'primary' => '#008E97', 'secondary' => '#FC4C02', 'off' => 82, 'def' => 76, 'st' => 78, 'coach' => 'Mike McDaniel'],
        ['id' => 'NYJ', 'name' => 'Jets',     'city' => 'New York',     'conf' => 'AFC', 'div' => 'East',  'primary' => '#125740', 'secondary' => '#000000', 'off' => 74, 'def' => 85, 'st' => 76, 'coach' => 'Aaron Glenn'],
        ['id' => 'NE',  'name' => 'Patriots', 'city' => 'New England',  'conf' => 'AFC', 'div' => 'East',  'primary' => '#002244', 'secondary' => '#C60C30', 'off' => 72, 'def' => 78, 'st' => 79, 'coach' => 'Mike Vrabel'],
        // AFC North
        ['id' => 'BAL', 'name' => 'Ravens',   'city' => 'Baltimore',    'conf' => 'AFC', 'div' => 'North', 'primary' => '#241773', 'secondary' => '#9E7C0C', 'off' => 91, 'def' => 86, 'st' => 83, 'coach' => 'John Harbaugh'],
        ['id' => 'CIN', 'name' => 'Bengals',  'city' => 'Cincinnati',   'conf' => 'AFC', 'div' => 'North', 'primary' => '#FB4F14', 'secondary' => '#000000', 'off' => 87, 'def' => 74, 'st' => 76, 'coach' => 'Zac Taylor'],
        ['id' => 'PIT', 'name' => 'Steelers', 'city' => 'Pittsburgh',   'conf' => 'AFC', 'div' => 'North', 'primary' => '#FFB612', 'secondary' => '#101820', 'off' => 78, 'def' => 88, 'st' => 82, 'coach' => 'Mike Tomlin'],
        ['id' => 'CLE', 'name' => 'Browns',   'city' => 'Cleveland',    'conf' => 'AFC', 'div' => 'North', 'primary' => '#311D00', 'secondary' => '#FF3C00', 'off' => 72, 'def' => 83, 'st' => 74, 'coach' => 'Kevin Stefanski'],
        // AFC South
        ['id' => 'HOU', 'name' => 'Texans',   'city' => 'Houston',      'conf' => 'AFC', 'div' => 'South', 'primary' => '#03202F', 'secondary' => '#A71930', 'off' => 84, 'def' => 84, 'st' => 80, 'coach' => 'DeMeco Ryans'],
        ['id' => 'IND', 'name' => 'Colts',    'city' => 'Indianapolis', 'conf' => 'AFC', 'div' => 'South', 'primary' => '#002C5F', 'secondary' => '#A5ACAF', 'off' => 80, 'def' => 78, 'st' => 77, 'coach' => 'Shane Steichen'],
        ['id' => 'JAX', 'name' => 'Jaguars',  'city' => 'Jacksonville', 'conf' => 'AFC', 'div' => 'South', 'primary' => '#006778', 'secondary' => '#D7A22A', 'off' => 78, 'def' => 74, 'st' => 75, 'coach' => 'Liam Coen'],
        ['id' => 'TEN', 'name' => 'Titans',   'city' => 'Tennessee',    'conf' => 'AFC', 'div' => 'South', 'primary' => '#0C2340', 'secondary' => '#4B92DB', 'off' => 70, 'def' => 76, 'st' => 74, 'coach' => 'Brian Callahan'],
        // AFC West
        ['id' => 'KC',  'name' => 'Chiefs',   'city' => 'Kansas City',  'conf' => 'AFC', 'div' => 'West',  'primary' => '#E31837', 'secondary' => '#FFB81C', 'off' => 92, 'def' => 85, 'st' => 84, 'coach' => 'Andy Reid'],
        ['id' => 'LAC', 'name' => 'Chargers', 'city' => 'Los Angeles',  'conf' => 'AFC', 'div' => 'West',  'primary' => '#0080C6', 'secondary' => '#FFC20E', 'off' => 83, 'def' => 82, 'st' => 78, 'coach' => 'Jim Harbaugh'],
        ['id' => 'DEN', 'name' => 'Broncos',  'city' => 'Denver',       'conf' => 'AFC', 'div' => 'West',  'primary' => '#FB4F14', 'secondary' => '#002244', 'off' => 81, 'def' => 87, 'st' => 80, 'coach' => 'Sean Payton'],
        ['id' => 'LV',  'name' => 'Raiders',  'city' => 'Las Vegas',    'conf' => 'AFC', 'div' => 'West',  'primary' => '#000000', 'secondary' => '#A5ACAF', 'off' => 74, 'def' => 76, 'st' => 77, 'coach' => 'Pete Carroll'],
        // NFC East
        ['id' => 'PHI', 'name' => 'Eagles',   'city' => 'Philadelphia', 'conf' => 'NFC', 'div' => 'East',  'primary' => '#004C54', 'secondary' => '#A5ACAF', 'off' => 92, 'def' => 89, 'st' => 82, 'coach' => 'Nick Sirianni'],
        ['id' => 'WAS', 'name' => 'Commanders','city' => 'Washington',  'conf' => 'NFC', 'div' => 'East',  'primary' => '#5A1414', 'secondary' => '#FFB612', 'off' => 86, 'def' => 78, 'st' => 77, 'coach' => 'Dan Quinn'],
        ['id' => 'DAL', 'name' => 'Cowboys',  'city' => 'Dallas',       'conf' => 'NFC', 'div' => 'East',  'primary' => '#003594', 'secondary' => '#869397', 'off' => 82, 'def' => 78, 'st' => 78, 'coach' => 'Brian Schottenheimer'],
        ['id' => 'NYG', 'name' => 'Giants',   'city' => 'New York',     'conf' => 'NFC', 'div' => 'East',  'primary' => '#0B2265', 'secondary' => '#A71930', 'off' => 70, 'def' => 76, 'st' => 73, 'coach' => 'Brian Daboll'],
        // NFC North
        ['id' => 'DET', 'name' => 'Lions',    'city' => 'Detroit',      'conf' => 'NFC', 'div' => 'North', 'primary' => '#0076B6', 'secondary' => '#B0B7BC', 'off' => 91, 'def' => 83, 'st' => 80, 'coach' => 'Dan Campbell'],
        ['id' => 'GB',  'name' => 'Packers',  'city' => 'Green Bay',    'conf' => 'NFC', 'div' => 'North', 'primary' => '#203731', 'secondary' => '#FFB612', 'off' => 85, 'def' => 84, 'st' => 79, 'coach' => 'Matt LaFleur'],
        ['id' => 'MIN', 'name' => 'Vikings',  'city' => 'Minnesota',    'conf' => 'NFC', 'div' => 'North', 'primary' => '#4F2683', 'secondary' => '#FFC62F', 'off' => 83, 'def' => 85, 'st' => 78, 'coach' => "Kevin O'Connell"],
        ['id' => 'CHI', 'name' => 'Bears',    'city' => 'Chicago',      'conf' => 'NFC', 'div' => 'North', 'primary' => '#0B162A', 'secondary' => '#C83803', 'off' => 79, 'def' => 80, 'st' => 77, 'coach' => 'Ben Johnson'],
        // NFC South
        ['id' => 'TB',  'name' => 'Buccaneers','city' => 'Tampa Bay',   'conf' => 'NFC', 'div' => 'South', 'primary' => '#D50A0A', 'secondary' => '#0A0A08', 'off' => 84, 'def' => 78, 'st' => 77, 'coach' => 'Todd Bowles'],
        ['id' => 'ATL', 'name' => 'Falcons',  'city' => 'Atlanta',      'conf' => 'NFC', 'div' => 'South', 'primary' => '#A71930', 'secondary' => '#000000', 'off' => 82, 'def' => 74, 'st' => 75, 'coach' => 'Raheem Morris'],
        ['id' => 'NO',  'name' => 'Saints',   'city' => 'New Orleans',  'conf' => 'NFC', 'div' => 'South', 'primary' => '#D3BC8D', 'secondary' => '#101820', 'off' => 74, 'def' => 76, 'st' => 76, 'coach' => 'Kellen Moore'],
        ['id' => 'CAR', 'name' => 'Panthers', 'city' => 'Carolina',     'conf' => 'NFC', 'div' => 'South', 'primary' => '#0085CA', 'secondary' => '#101820', 'off' => 74, 'def' => 72, 'st' => 73, 'coach' => 'Dave Canales'],
        // NFC West
        ['id' => 'SF',  'name' => '49ers',    'city' => 'San Francisco','conf' => 'NFC', 'div' => 'West',  'primary' => '#AA0000', 'secondary' => '#B3995D', 'off' => 88, 'def' => 86, 'st' => 81, 'coach' => 'Kyle Shanahan'],
        ['id' => 'LAR', 'name' => 'Rams',     'city' => 'Los Angeles',  'conf' => 'NFC', 'div' => 'West',  'primary' => '#003594', 'secondary' => '#FFA300', 'off' => 87, 'def' => 82, 'st' => 79, 'coach' => 'Sean McVay'],
        ['id' => 'SEA', 'name' => 'Seahawks', 'city' => 'Seattle',      'conf' => 'NFC', 'div' => 'West',  'primary' => '#002244', 'secondary' => '#69BE28', 'off' => 81, 'def' => 79, 'st' => 78, 'coach' => 'Mike Macdonald'],
        ['id' => 'ARI', 'name' => 'Cardinals','city' => 'Arizona',      'conf' => 'NFC', 'div' => 'West',  'primary' => '#97233F', 'secondary' => '#000000', 'off' => 78, 'def' => 74, 'st' => 74, 'coach' => 'Jonathan Gannon'],
    ];

    /** Star players per team: QB, RB, WR1, WR2, TE, DEF star, K */
    public const PLAYERS = [
        'BUF' => [['name'=>'Josh Allen','pos'=>'QB','ovr'=>96,'num'=>17],['name'=>'James Cook','pos'=>'RB','ovr'=>87,'num'=>4],['name'=>'Khalil Shakir','pos'=>'WR','ovr'=>84,'num'=>10],['name'=>'Keon Coleman','pos'=>'WR','ovr'=>78,'num'=>0],['name'=>'Dalton Kincaid','pos'=>'TE','ovr'=>82,'num'=>86],['name'=>'Matt Milano','pos'=>'LB','ovr'=>89,'num'=>58],['name'=>'Tyler Bass','pos'=>'K','ovr'=>84,'num'=>2]],
        'MIA' => [['name'=>'Tua Tagovailoa','pos'=>'QB','ovr'=>84,'num'=>1],['name'=>"De'Von Achane",'pos'=>'RB','ovr'=>87,'num'=>28],['name'=>'Tyreek Hill','pos'=>'WR','ovr'=>95,'num'=>10],['name'=>'Jaylen Waddle','pos'=>'WR','ovr'=>87,'num'=>17],['name'=>'Jonnu Smith','pos'=>'TE','ovr'=>79,'num'=>9],['name'=>'Jalen Ramsey','pos'=>'CB','ovr'=>89,'num'=>5],['name'=>'Jason Sanders','pos'=>'K','ovr'=>82,'num'=>7]],
        'NYJ' => [['name'=>'Justin Fields','pos'=>'QB','ovr'=>78,'num'=>7],['name'=>'Breece Hall','pos'=>'RB','ovr'=>88,'num'=>20],['name'=>'Garrett Wilson','pos'=>'WR','ovr'=>89,'num'=>5],['name'=>'Allen Lazard','pos'=>'WR','ovr'=>74,'num'=>10],['name'=>'Tyler Conklin','pos'=>'TE','ovr'=>76,'num'=>83],['name'=>'Sauce Gardner','pos'=>'CB','ovr'=>94,'num'=>1],['name'=>'Greg Zuerlein','pos'=>'K','ovr'=>78,'num'=>1]],
        'NE'  => [['name'=>'Drake Maye','pos'=>'QB','ovr'=>80,'num'=>10],['name'=>'Rhamondre Stevenson','pos'=>'RB','ovr'=>82,'num'=>38],['name'=>'Stefon Diggs','pos'=>'WR','ovr'=>85,'num'=>1],['name'=>'Kayshon Boutte','pos'=>'WR','ovr'=>74,'num'=>9],['name'=>'Hunter Henry','pos'=>'TE','ovr'=>78,'num'=>85],['name'=>'Christian Barmore','pos'=>'DT','ovr'=>84,'num'=>90],['name'=>'Andy Borregales','pos'=>'K','ovr'=>76,'num'=>3]],
        'BAL' => [['name'=>'Lamar Jackson','pos'=>'QB','ovr'=>97,'num'=>8],['name'=>'Derrick Henry','pos'=>'RB','ovr'=>92,'num'=>22],['name'=>'Zay Flowers','pos'=>'WR','ovr'=>85,'num'=>4],['name'=>'Rashod Bateman','pos'=>'WR','ovr'=>80,'num'=>7],['name'=>'Mark Andrews','pos'=>'TE','ovr'=>88,'num'=>89],['name'=>'Roquan Smith','pos'=>'LB','ovr'=>92,'num'=>0],['name'=>'Justin Tucker','pos'=>'K','ovr'=>90,'num'=>9]],
        'CIN' => [['name'=>'Joe Burrow','pos'=>'QB','ovr'=>93,'num'=>9],['name'=>'Chase Brown','pos'=>'RB','ovr'=>82,'num'=>30],['name'=>"Ja'Marr Chase",'pos'=>'WR','ovr'=>97,'num'=>1],['name'=>'Tee Higgins','pos'=>'WR','ovr'=>89,'num'=>5],['name'=>'Mike Gesicki','pos'=>'TE','ovr'=>78,'num'=>88],['name'=>'Trey Hendrickson','pos'=>'DE','ovr'=>92,'num'=>91],['name'=>'Evan McPherson','pos'=>'K','ovr'=>82,'num'=>2]],
        'PIT' => [['name'=>'Aaron Rodgers','pos'=>'QB','ovr'=>84,'num'=>8],['name'=>'Jaylen Warren','pos'=>'RB','ovr'=>80,'num'=>30],['name'=>'DK Metcalf','pos'=>'WR','ovr'=>88,'num'=>4],['name'=>'Calvin Austin III','pos'=>'WR','ovr'=>76,'num'=>19],['name'=>'Pat Freiermuth','pos'=>'TE','ovr'=>80,'num'=>88],['name'=>'T.J. Watt','pos'=>'OLB','ovr'=>97,'num'=>90],['name'=>'Chris Boswell','pos'=>'K','ovr'=>89,'num'=>9]],
        'CLE' => [['name'=>'Kenny Pickett','pos'=>'QB','ovr'=>74,'num'=>8],['name'=>'Quinshon Judkins','pos'=>'RB','ovr'=>80,'num'=>25],['name'=>'Jerry Jeudy','pos'=>'WR','ovr'=>82,'num'=>3],['name'=>'Cedric Tillman','pos'=>'WR','ovr'=>76,'num'=>89],['name'=>'David Njoku','pos'=>'TE','ovr'=>84,'num'=>85],['name'=>'Myles Garrett','pos'=>'DE','ovr'=>98,'num'=>95],['name'=>'Andre Szmyt','pos'=>'K','ovr'=>74,'num'=>3]],
        'HOU' => [['name'=>'C.J. Stroud','pos'=>'QB','ovr'=>87,'num'=>7],['name'=>'Nick Chubb','pos'=>'RB','ovr'=>82,'num'=>27],['name'=>'Nico Collins','pos'=>'WR','ovr'=>90,'num'=>12],['name'=>'Christian Kirk','pos'=>'WR','ovr'=>80,'num'=>13],['name'=>'Dalton Schultz','pos'=>'TE','ovr'=>80,'num'=>86],['name'=>'Will Anderson Jr.','pos'=>'DE','ovr'=>89,'num'=>51],['name'=>"Ka'imi Fairbairn",'pos'=>'K','ovr'=>82,'num'=>7]],
        'IND' => [['name'=>'Daniel Jones','pos'=>'QB','ovr'=>78,'num'=>17],['name'=>'Jonathan Taylor','pos'=>'RB','ovr'=>90,'num'=>28],['name'=>'Michael Pittman Jr.','pos'=>'WR','ovr'=>84,'num'=>11],['name'=>'Josh Downs','pos'=>'WR','ovr'=>80,'num'=>1],['name'=>'Tyler Warren','pos'=>'TE','ovr'=>80,'num'=>87],['name'=>'DeForest Buckner','pos'=>'DT','ovr'=>89,'num'=>99],['name'=>'Spencer Shrader','pos'=>'K','ovr'=>76,'num'=>9]],
        'JAX' => [['name'=>'Trevor Lawrence','pos'=>'QB','ovr'=>82,'num'=>16],['name'=>'Travis Etienne','pos'=>'RB','ovr'=>82,'num'=>1],['name'=>'Brian Thomas Jr.','pos'=>'WR','ovr'=>88,'num'=>7],['name'=>'Travis Hunter','pos'=>'WR','ovr'=>84,'num'=>12],['name'=>'Brenton Strange','pos'=>'TE','ovr'=>76,'num'=>85],['name'=>'Josh Hines-Allen','pos'=>'DE','ovr'=>89,'num'=>41],['name'=>'Cam Little','pos'=>'K','ovr'=>78,'num'=>3]],
        'TEN' => [['name'=>'Cam Ward','pos'=>'QB','ovr'=>76,'num'=>1],['name'=>'Tony Pollard','pos'=>'RB','ovr'=>80,'num'=>20],['name'=>'Calvin Ridley','pos'=>'WR','ovr'=>83,'num'=>0],['name'=>'Tyler Lockett','pos'=>'WR','ovr'=>78,'num'=>16],['name'=>'Chig Okonkwo','pos'=>'TE','ovr'=>76,'num'=>85],['name'=>'Jeffery Simmons','pos'=>'DT','ovr'=>91,'num'=>98],['name'=>'Nick Folk','pos'=>'K','ovr'=>78,'num'=>6]],
        'KC'  => [['name'=>'Patrick Mahomes','pos'=>'QB','ovr'=>98,'num'=>15],['name'=>'Isiah Pacheco','pos'=>'RB','ovr'=>82,'num'=>10],['name'=>'Xavier Worthy','pos'=>'WR','ovr'=>84,'num'=>1],['name'=>'Rashee Rice','pos'=>'WR','ovr'=>85,'num'=>4],['name'=>'Travis Kelce','pos'=>'TE','ovr'=>92,'num'=>87],['name'=>'Chris Jones','pos'=>'DT','ovr'=>96,'num'=>95],['name'=>'Harrison Butker','pos'=>'K','ovr'=>89,'num'=>7]],
        'LAC' => [['name'=>'Justin Herbert','pos'=>'QB','ovr'=>89,'num'=>10],['name'=>'Omarion Hampton','pos'=>'RB','ovr'=>82,'num'=>28],['name'=>'Ladd McConkey','pos'=>'WR','ovr'=>85,'num'=>15],['name'=>'Quentin Johnston','pos'=>'WR','ovr'=>78,'num'=>1],['name'=>'Will Dissly','pos'=>'TE','ovr'=>76,'num'=>88],['name'=>'Khalil Mack','pos'=>'OLB','ovr'=>90,'num'=>52],['name'=>'Cameron Dicker','pos'=>'K','ovr'=>85,'num'=>11]],
        'DEN' => [['name'=>'Bo Nix','pos'=>'QB','ovr'=>82,'num'=>10],['name'=>'R.J. Harvey','pos'=>'RB','ovr'=>78,'num'=>20],['name'=>'Courtland Sutton','pos'=>'WR','ovr'=>85,'num'=>14],['name'=>'Marvin Mims Jr.','pos'=>'WR','ovr'=>80,'num'=>19],['name'=>'Evan Engram','pos'=>'TE','ovr'=>82,'num'=>1],['name'=>'Pat Surtain II','pos'=>'CB','ovr'=>96,'num'=>2],['name'=>'Wil Lutz','pos'=>'K','ovr'=>82,'num'=>16]],
        'LV'  => [['name'=>'Geno Smith','pos'=>'QB','ovr'=>82,'num'=>7],['name'=>'Ashton Jeanty','pos'=>'RB','ovr'=>85,'num'=>2],['name'=>'Jakobi Meyers','pos'=>'WR','ovr'=>80,'num'=>16],['name'=>'Tre Tucker','pos'=>'WR','ovr'=>76,'num'=>11],['name'=>'Brock Bowers','pos'=>'TE','ovr'=>91,'num'=>89],['name'=>'Maxx Crosby','pos'=>'DE','ovr'=>94,'num'=>98],['name'=>'Daniel Carlson','pos'=>'K','ovr'=>86,'num'=>2]],
        'PHI' => [['name'=>'Jalen Hurts','pos'=>'QB','ovr'=>91,'num'=>1],['name'=>'Saquon Barkley','pos'=>'RB','ovr'=>96,'num'=>26],['name'=>'A.J. Brown','pos'=>'WR','ovr'=>93,'num'=>11],['name'=>'DeVonta Smith','pos'=>'WR','ovr'=>87,'num'=>6],['name'=>'Dallas Goedert','pos'=>'TE','ovr'=>82,'num'=>88],['name'=>'Jalen Carter','pos'=>'DT','ovr'=>92,'num'=>98],['name'=>'Jake Elliott','pos'=>'K','ovr'=>86,'num'=>4]],
        'WAS' => [['name'=>'Jayden Daniels','pos'=>'QB','ovr'=>90,'num'=>5],['name'=>'Brian Robinson Jr.','pos'=>'RB','ovr'=>82,'num'=>8],['name'=>'Terry McLaurin','pos'=>'WR','ovr'=>89,'num'=>17],['name'=>'Deebo Samuel','pos'=>'WR','ovr'=>84,'num'=>1],['name'=>'Zach Ertz','pos'=>'TE','ovr'=>78,'num'=>86],['name'=>'Bobby Wagner','pos'=>'LB','ovr'=>88,'num'=>54],['name'=>'Matt Gay','pos'=>'K','ovr'=>82,'num'=>8]],
        'DAL' => [['name'=>'Dak Prescott','pos'=>'QB','ovr'=>86,'num'=>4],['name'=>'Javonte Williams','pos'=>'RB','ovr'=>78,'num'=>33],['name'=>'CeeDee Lamb','pos'=>'WR','ovr'=>94,'num'=>88],['name'=>'George Pickens','pos'=>'WR','ovr'=>84,'num'=>3],['name'=>'Jake Ferguson','pos'=>'TE','ovr'=>80,'num'=>87],['name'=>'Micah Parsons','pos'=>'OLB','ovr'=>96,'num'=>11],['name'=>'Brandon Aubrey','pos'=>'K','ovr'=>90,'num'=>17]],
        'NYG' => [['name'=>'Russell Wilson','pos'=>'QB','ovr'=>76,'num'=>3],['name'=>'Cam Skattebo','pos'=>'RB','ovr'=>78,'num'=>44],['name'=>'Malik Nabers','pos'=>'WR','ovr'=>88,'num'=>1],['name'=>"Wan'Dale Robinson",'pos'=>'WR','ovr'=>78,'num'=>17],['name'=>'Theo Johnson','pos'=>'TE','ovr'=>74,'num'=>84],['name'=>'Dexter Lawrence','pos'=>'DT','ovr'=>94,'num'=>97],['name'=>'Graham Gano','pos'=>'K','ovr'=>80,'num'=>5]],
        'DET' => [['name'=>'Jared Goff','pos'=>'QB','ovr'=>89,'num'=>16],['name'=>'Jahmyr Gibbs','pos'=>'RB','ovr'=>95,'num'=>26],['name'=>'Amon-Ra St. Brown','pos'=>'WR','ovr'=>94,'num'=>14],['name'=>'Jameson Williams','pos'=>'WR','ovr'=>84,'num'=>9],['name'=>'Sam LaPorta','pos'=>'TE','ovr'=>87,'num'=>87],['name'=>'Aidan Hutchinson','pos'=>'DE','ovr'=>93,'num'=>97],['name'=>'Jake Bates','pos'=>'K','ovr'=>82,'num'=>39]],
        'GB'  => [['name'=>'Jordan Love','pos'=>'QB','ovr'=>85,'num'=>10],['name'=>'Josh Jacobs','pos'=>'RB','ovr'=>88,'num'=>8],['name'=>'Matthew Golden','pos'=>'WR','ovr'=>82,'num'=>22],['name'=>'Jayden Reed','pos'=>'WR','ovr'=>80,'num'=>11],['name'=>'Tucker Kraft','pos'=>'TE','ovr'=>84,'num'=>85],['name'=>'Rashan Gary','pos'=>'DE','ovr'=>87,'num'=>52],['name'=>'Brandon McManus','pos'=>'K','ovr'=>82,'num'=>6]],
        'MIN' => [['name'=>'J.J. McCarthy','pos'=>'QB','ovr'=>80,'num'=>9],['name'=>'Aaron Jones','pos'=>'RB','ovr'=>82,'num'=>33],['name'=>'Justin Jefferson','pos'=>'WR','ovr'=>99,'num'=>18],['name'=>'Jordan Addison','pos'=>'WR','ovr'=>84,'num'=>3],['name'=>'T.J. Hockenson','pos'=>'TE','ovr'=>85,'num'=>87],['name'=>'Andrew Van Ginkel','pos'=>'OLB','ovr'=>87,'num'=>43],['name'=>'Will Reichard','pos'=>'K','ovr'=>78,'num'=>16]],
        'CHI' => [['name'=>'Caleb Williams','pos'=>'QB','ovr'=>82,'num'=>18],['name'=>"D'Andre Swift",'pos'=>'RB','ovr'=>82,'num'=>4],['name'=>'DJ Moore','pos'=>'WR','ovr'=>88,'num'=>2],['name'=>'Rome Odunze','pos'=>'WR','ovr'=>82,'num'=>15],['name'=>'Colston Loveland','pos'=>'TE','ovr'=>78,'num'=>88],['name'=>'Montez Sweat','pos'=>'DE','ovr'=>87,'num'=>98],['name'=>'Cairo Santos','pos'=>'K','ovr'=>80,'num'=>2]],
        'TB'  => [['name'=>'Baker Mayfield','pos'=>'QB','ovr'=>86,'num'=>6],['name'=>'Bucky Irving','pos'=>'RB','ovr'=>84,'num'=>7],['name'=>'Mike Evans','pos'=>'WR','ovr'=>91,'num'=>13],['name'=>'Chris Godwin','pos'=>'WR','ovr'=>85,'num'=>14],['name'=>'Cade Otton','pos'=>'TE','ovr'=>78,'num'=>88],['name'=>'Vita Vea','pos'=>'DT','ovr'=>90,'num'=>50],['name'=>'Chase McLaughlin','pos'=>'K','ovr'=>84,'num'=>4]],
        'ATL' => [['name'=>'Michael Penix Jr.','pos'=>'QB','ovr'=>82,'num'=>9],['name'=>'Bijan Robinson','pos'=>'RB','ovr'=>93,'num'=>7],['name'=>'Drake London','pos'=>'WR','ovr'=>88,'num'=>5],['name'=>'Darnell Mooney','pos'=>'WR','ovr'=>80,'num'=>1],['name'=>'Kyle Pitts','pos'=>'TE','ovr'=>82,'num'=>8],['name'=>'Jessie Bates III','pos'=>'S','ovr'=>90,'num'=>3],['name'=>'Younghoe Koo','pos'=>'K','ovr'=>84,'num'=>6]],
        'NO'  => [['name'=>'Spencer Rattler','pos'=>'QB','ovr'=>74,'num'=>2],['name'=>'Alvin Kamara','pos'=>'RB','ovr'=>86,'num'=>41],['name'=>'Chris Olave','pos'=>'WR','ovr'=>84,'num'=>12],['name'=>'Rashid Shaheed','pos'=>'WR','ovr'=>78,'num'=>22],['name'=>'Juwan Johnson','pos'=>'TE','ovr'=>76,'num'=>83],['name'=>'Cameron Jordan','pos'=>'DE','ovr'=>83,'num'=>94],['name'=>'Blake Grupe','pos'=>'K','ovr'=>76,'num'=>19]],
        'CAR' => [['name'=>'Bryce Young','pos'=>'QB','ovr'=>76,'num'=>9],['name'=>'Chuba Hubbard','pos'=>'RB','ovr'=>82,'num'=>30],['name'=>'Tetairoa McMillan','pos'=>'WR','ovr'=>80,'num'=>4],['name'=>'Xavier Legette','pos'=>'WR','ovr'=>76,'num'=>17],['name'=>'Tommy Tremble','pos'=>'TE','ovr'=>74,'num'=>82],['name'=>'Derrick Brown','pos'=>'DT','ovr'=>88,'num'=>95],['name'=>'Ryan Fitzgerald','pos'=>'K','ovr'=>74,'num'=>3]],
        'SF'  => [['name'=>'Brock Purdy','pos'=>'QB','ovr'=>86,'num'=>13],['name'=>'Christian McCaffrey','pos'=>'RB','ovr'=>94,'num'=>23],['name'=>'Ricky Pearsall','pos'=>'WR','ovr'=>80,'num'=>14],['name'=>'Jauan Jennings','pos'=>'WR','ovr'=>82,'num'=>15],['name'=>'George Kittle','pos'=>'TE','ovr'=>95,'num'=>85],['name'=>'Nick Bosa','pos'=>'DE','ovr'=>96,'num'=>97],['name'=>'Jake Moody','pos'=>'K','ovr'=>78,'num'=>4]],
        'LAR' => [['name'=>'Matthew Stafford','pos'=>'QB','ovr'=>87,'num'=>9],['name'=>'Kyren Williams','pos'=>'RB','ovr'=>86,'num'=>23],['name'=>'Puka Nacua','pos'=>'WR','ovr'=>91,'num'=>17],['name'=>'Davante Adams','pos'=>'WR','ovr'=>90,'num'=>17],['name'=>'Tyler Higbee','pos'=>'TE','ovr'=>78,'num'=>89],['name'=>'Jared Verse','pos'=>'OLB','ovr'=>88,'num'=>8],['name'=>'Joshua Karty','pos'=>'K','ovr'=>80,'num'=>16]],
        'SEA' => [['name'=>'Sam Darnold','pos'=>'QB','ovr'=>82,'num'=>14],['name'=>'Kenneth Walker III','pos'=>'RB','ovr'=>84,'num'=>9],['name'=>'Jaxon Smith-Njigba','pos'=>'WR','ovr'=>87,'num'=>11],['name'=>'Cooper Kupp','pos'=>'WR','ovr'=>85,'num'=>10],['name'=>'AJ Barner','pos'=>'TE','ovr'=>76,'num'=>88],['name'=>'Leonard Williams','pos'=>'DT','ovr'=>89,'num'=>99],['name'=>'Jason Myers','pos'=>'K','ovr'=>82,'num'=>5]],
        'ARI' => [['name'=>'Kyler Murray','pos'=>'QB','ovr'=>84,'num'=>1],['name'=>'James Conner','pos'=>'RB','ovr'=>84,'num'=>6],['name'=>'Marvin Harrison Jr.','pos'=>'WR','ovr'=>86,'num'=>18],['name'=>'Michael Wilson','pos'=>'WR','ovr'=>78,'num'=>14],['name'=>'Trey McBride','pos'=>'TE','ovr'=>90,'num'=>85],['name'=>'Budda Baker','pos'=>'S','ovr'=>92,'num'=>3],['name'=>'Chad Ryland','pos'=>'K','ovr'=>76,'num'=>37]],
    ];

    public static function getTeam(string $id): ?array {
        foreach (self::TEAMS as $t) {
            if ($t['id'] === $id) return $t;
        }
        return null;
    }

    public static function getPlayers(string $teamId): array {
        $starters = self::PLAYERS[$teamId] ?? [];
        $tagged = array_map(static fn($p) => $p + ['starter' => true, 'role' => $p['pos']], $starters);
        $backups = self::generateBackups($teamId, $starters);
        return array_merge($tagged, $backups);
    }

    private static function generateBackups(string $teamId, array $starters): array {
        $seed = array_sum(array_map('ord', str_split($teamId)));
        $byPos = [];
        foreach ($starters as $p) $byPos[$p['pos']] = $p;

        $templates = [
            ['QB', ['Trey Smith', 'Malik Cunningham', 'Jake Fromm', 'Carson Wentz', 'Tim Boyle', 'Jarrett Stidham', 'Nick Mullens']],
            ['RB', ['Deuce Vaughn', 'Zach Charbonnet', 'Dameon Pierce', 'Elijah Mitchell', 'Kene Nwangwu', 'Kendre Miller', 'Tyler Allgeier']],
            ['WR', ['Isaiah Hodgins', 'Simi Fehoko', 'Trent Sherfield', 'Kalif Raymond', 'Tyler Boyd', 'Rondale Moore', 'Skyy Moore']],
            ['K',  ['Riley Patterson', 'Cade York', 'Anders Carlson', 'Eddy Pineiro', 'Ryan Succop', 'Dustin Hopkins', 'Michael Badgley']],
        ];

        $out = [];
        foreach ($templates as $i => [$pos, $names]) {
            $starter = $byPos[$pos] ?? null;
            if (!$starter) continue;
            $drop = 8 + ($seed % 6);
            $out[] = [
                'name'    => $names[($seed + $i) % count($names)],
                'pos'     => $pos,
                'ovr'     => max(60, $starter['ovr'] - $drop),
                'num'     => 30 + ($seed + $i * 7) % 60,
                'starter' => false,
                'role'    => "{$pos}2",
            ];
        }
        return $out;
    }
}
