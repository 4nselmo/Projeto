<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class JogadorGol extends Model
{
    protected $table = 'jogador_gols';

    public function jogadores()
    {
        return $this->belongsToMany(Jogador::class);
    }
}