<?php
namespace App\Models;use Illuminate\Database\Eloquent\Concerns\HasUuids;use Illuminate\Database\Eloquent\Model;
class VendorSubmissionUnit extends Model{use HasUuids;protected $guarded=[];public function submission(){return $this->belongsTo(VendorSubmission::class,'vendor_submission_id');}public function documents(){return $this->hasMany(VendorDocument::class);}}
