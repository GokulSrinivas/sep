<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use Validator;
use App\registration as Registration;
use Illuminate\Support\Facades\Session;;

use Imagick;

class HomeController extends Controller
{

    public function index()
    {
        return view('welcome');
    }

    public function area_of_training()
    {
        return view('area_of_training');
    }
    public function target_audience()
    {
        return view('target_audience');
    }
    public function resource_person()
    {
        return view('resource_person');
    }
    public function registration()
    {
        if(Session::has('college'))
        {
            $count = Registration::where('isnit','=','1')->count();
            if($count>=150)
                return view('registrations_full');
        }
        else
        {
            $count = Registration::where('isnit','=','0')->count();
            if($count>=150)
                return view('registrations_full');
        }
        return view('registration');
    }
    public function registrationshow()
    {
        return view('reghome');
    }
    public function register_nitt()
    {
        $count = Registration::where('isnit','=','1')->count();
        if($count>=150)
            return view('registrations_full');
        return view('register_nitt');
    }
    public function sponsorship_opportunity()
    {
        return view('sponsorship_opportunity');
    }


    public function LDAPfill(Request $request)
    {

        $rollno = $request->get('roll');

        $ldapconn = ldap_connect("10.0.0.38")
            or die("Could not connect to LDAP server.");

        ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($ldapconn, LDAP_OPT_REFERRALS, 0);
    
        $ldapbind = @ldap_bind($ldapconn, "CN=106114073,OU=2014,OU=UG,OU=CSE,DC=octa,DC=edu", "Password2");
        
        if ($ldapbind)
        {
            $sr=ldap_search($ldapconn,"DC=octa,DC=edu", "cn=".$rollno);
            $ldaparr = ldap_get_entries($ldapconn,$sr); 

            $name = $ldaparr[0]['displayname'][0];
            $desc = $ldaparr[0]['description'][0];
            
            $distname = $ldaparr[0]['distinguishedname'][0];


            $expdesc = explode(" ",$desc);
            $expdistname = explode(",",$distname);

            $inputlist = array(
                'name'          => $name,
                'degree'        => explode("=",$expdistname[2])[1],
                'course'        => $expdesc[1],
                'branch'        => $expdesc[2],
                'college'       => "NIT-Trichy Tanjore Main Road,National Highway 67,Tiruchirappalli - 620015,Tamil Nadu",
            );

            // var_dump($expdesc);
            // var_dump($expdistname);
            // print_r($ldaparr[0]);
            return redirect('/registration/form')->with($inputlist);          
        } 
        else 
        {
            echo "LDAP bind failed...\n";
        }


    }



    public function store_registration(Request $request)
    {
        $messages = [
       'digits_between' => 'The :attribute must be 10 digits.',
        ];
        $v = Validator::make($request->all(), [
        'name'          => 'required|min:3|max:255',
        'gender'        => 'required|in:male,female',
        'degree'        => 'required|in:UG,PG',
        'course'        => 'required|in:B.E,B.Tech,B.Sc,B.Com,B.A,M.Tech,M.B.A,M.Sc,other',
        'other_course'  => 'required_if:course,other',
        'year'          => 'required|in:Final year U.G.,Final year P.G.,Pre-final year of Engineering',
        'branch'        => 'required|min:2|max:20',
        'college'       => 'required|min:10|max:150',
        'email'         => 'required|email|unique:registrations',
        'phone'         => 'required|integer|digits_between:10,10',
        'guardian_phone'=> 'required|integer|digits_between:10,10',
        'dd_no'         => 'required|min:5|max:10',
        'dd_date'       => 'required|min:10|max:30',
        'bank_name'     => 'required|min:2|max:20',
        'reason'        => 'required|min:20|max:200',
        'isnit'         => 'required|in:0,1',

        ],$messages);
    //redirect to registration page with errors if there is any
        if ($v->fails())
        {
            return redirect('/registration/form')->withErrors($v->errors())->withInput();
        }

    //else
        if($request->isnit==1)
        {
            $count = Registration::where('isnit','=','1')->count();
            if($count>=150)
                return view('registrations_full');
        }
        else
        {
            $count = Registration::where('isnit','=','0')->count();
            if($count>=150)
                return view('registrations_full');
        }
        if(strcmp($request->course,"other")==0)
            $request->course = $request->other_course;
        if(strcmp($request->degree,"UG")==0)
            $amount = 960;
        else
            $amount = 1560;
        $registration                       = new Registration();
        $registration->name                 = $request->name;
        $registration->gender               = $request->gender; 
        $registration->degree               = $request->degree;
        $registration->course               = $request->course;
        $registration->year                 = $request->year;
        $registration->dept                 = $request->branch;
        $registration->college_address      = $request->college;
        $registration->email                = $request->email;
        $registration->mobile_no            = $request->phone;
        $registration->guardian_mobile_no   = $request->guardian_phone;
        $registration->amount               = $amount;
        $registration->dd_no                = $request->dd_no;
        $registration->dd_date              = $request->dd_date;
        $registration->bank_name            = $request->bank_name;
        $registration->reason               = $request->reason;
        $registration->isnit                = $request->isnit;
        
        //save the record to table
        $save_status = $registration->save();
        if(!$save_status)
        {
            return redirect('/registration');
        }
        //update the reg_id of the inserted record
        $registration->reg_id               = $registration->id+1000;
        $registration->save();


        //generating the pdf 
        $path=getcwd();
            
        $im_page_1 = imagecreatefromjpeg($path.'/images/Page1.jpg');
    
        $black = imagecolorallocate($im_page_1, 0, 0, 0);
        $font = $path.'/font/Helvetica.otf';
        $d = strtotime("now");
        $today = date("d-m-Y", $d);
        $college = $registration->college_address;
        $words = explode(" ", $college);
        $lines = [""];
        $k=0;
        foreach($words as $word)
        {
            if(strlen($lines[$k]) + strlen($word) <= 50)
                $lines[$k].=" ".$word;
            else
            {
                $k++;
                $lines[$k]="";
                $lines[$k].=" ".$word;
            }

        }
        $k=0;
        foreach($lines as $line)
        {
            imagettftext($im_page_1,35,0,740,1475+$k*50, $black, $font,$line);
            $k++;
        }
        imagettftext($im_page_1,35,0,2000,1065, $black, $font,$registration->reg_id);
        imagettftext($im_page_1,35,0,750,1325, $black, $font,$registration->name);
        imagettftext($im_page_1,35,0,750,1625, $black, $font,$registration->degree);
        imagettftext($im_page_1,35,0,750,1775, $black, $font,$registration->course);
        imagettftext($im_page_1,35,0,540,2175, $black, $font,$today);
        $filename_page_1 = $registration->reg_id.".png";
        imagepng($im_page_1,$filename_page_1);

        $pdf = new Imagick(array($filename_page_1));
        $pdf->setImageFormat( "pdf" );

        $filename_pdf = $registration->reg_id.'.pdf';
        if (!$pdf->writeImages($filename_pdf, true)) {
            die('Could not write!');
        }


        header('Content-Type: application/pdf');
        header("Content-Disposition: inline; filename=".$filename_pdf);
        @readfile($filename_pdf);

        $delete_status_page_1 = unlink($filename_page_1);
        $delete_status_pdf = unlink($filename_pdf);

    }
    

}
