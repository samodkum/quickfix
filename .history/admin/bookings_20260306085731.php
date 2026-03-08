<?php
session_start();
include 'db.php';

error_reporting(E_ALL);
ini_set('display_errors',1);

if(isset($_POST['confirm_booking']))
{
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $service_id = $_POST['service_id'];
    $date = $_POST['date'];
    $time = $_POST['time'];

    try{

        $conn->beginTransaction();

        // available technician check
        $stmt = $conn->prepare("SELECT id FROM technicians WHERE service_id=? AND status='available' LIMIT 1");
        $stmt->execute([$service_id]);
        $tech = $stmt->fetch();

        if(!$tech){
            throw new Exception("No technician available");
        }

        $technician_id = $tech['id'];

        // booking insert
        $stmt = $conn->prepare("INSERT INTO bookings(user_name,email,phone,service_id,technician_id,booking_date,booking_time) VALUES(?,?,?,?,?,?,?)");

        $stmt->execute([
            $name,
            $email,
            $phone,
            $service_id,
            $technician_id,
            $date,
            $time
        ]);

        $conn->commit();

        echo "<script>alert('Booking Successful');window.location='thankyou.php';</script>";

    }
    catch(Exception $e){

        $conn->rollBack();
        echo "Booking Failed: ".$e->getMessage();

    }
}
?>