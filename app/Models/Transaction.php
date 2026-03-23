namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model {
    protected $fillable = ['user_id', 'type', 'amount', 'method', 'details', 'proof_image', 'status'];
}
