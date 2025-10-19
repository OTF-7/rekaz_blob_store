<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BlobStorage Model - Stores actual blob data for database backend
 * 
 * @property int $id Auto-incrementing primary key
 * @property string $blob_id Foreign key to blobs table
 * @property string $data Base64 encoded blob data
 */
class BlobStorage extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'blob_data';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'blob_id',
        'data',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the blob metadata record that owns this storage record.
     */
    public function blob(): BelongsTo
    {
        return $this->belongsTo(Blob::class, 'blob_id', 'id');
    }

    /**
     * Get the decoded binary data.
     */
    public function getDecodedData(): string
    {
        return base64_decode($this->data);
    }

    /**
     * Set the data attribute by encoding binary data to base64.
     */
    public function setDataAttribute(string $value): void
    {
        // If the value is already base64 encoded, store as-is
        // Otherwise, encode it
        if (base64_encode(base64_decode($value, true)) === $value) {
            $this->attributes['data'] = $value;
        } else {
            $this->attributes['data'] = base64_encode($value);
        }
    }
}
