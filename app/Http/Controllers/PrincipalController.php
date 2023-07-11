<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Jogador;
use App\Models\JogadorGol;
use App\Models\Equipe;
use DB;

class PrincipalController extends Controller
{
    public function index()
    {
        $jogadores = $this->getJogadores();
        $equipes = $this->getEquipes();
        return view('index',compact('jogadores', 'equipes'));
    }

    public function store(Request $request){
        $jogador = new Jogador();
        $jogador->nome = $request->nome;
        $jogador->save();
        return $this->getJogadores();
    }

    public function update(Request $request){
        $jogador = Jogador::find($request->id);
        $jogador->nome = $request->nome;
        $jogador->update();
        return $this->getJogadores();
    }

    public function getJogadores()
    {
        $jogadores = Jogador::select(DB::raw('jogadores.id, jogadores.nome, sum(jg.gols) as gols, sum(jg.gols_sofridos) as gols_sofridos, sum(gol_contra) as gol_contra'))
        ->leftJoin('jogador_gols As jg', 'jg.jogador_id', '=', 'jogadores.id')
        ->groupBy('jogadores.id')
        ->orderBy('gols', 'desc')
        ->get();
        return $jogadores;
    }

    public function storeGol(Request $request)
    {
        $gols = new JogadorGol();
        $gols->jogador_id = $request->jogadorId;
        $gols->equipe_id = $request->equipe_id;
        $gols->gols = $request->gols;
        $gols->gols_sofridos = $request->gols_sofridos;
        $gols->data = $request->data;
        $gols->save();

        $data = new class{};
        $data->jogador_gols = $this->getGolsJogador($request);
        $data->jogadores = $this->getJogadores();
        return response()->json($data);

    }

    public function updateGol(Request $request)
    {
        $jogadorGol = JogadorGol::find($request->id);
        if($request->gols != '')
            $jogadorGol->gols = $request->gols;
        if($request->gols_sofridos != '')
            $jogadorGol->gols_sofridos = $request->gols_sofridos;
        $jogadorGol->equipe_id = $request->equipe_id;
        $jogadorGol->data = $request->data;
        $jogadorGol->update();

        $data = new class{};
        $data->jogador_gols = $this->getGolsJogador($request);
        $data->jogadores = $this->getJogadores();
        return response()->json($data);

    }

    public function getGolsJogador(Request $request)
    {
        $jogador_gols = JogadorGol::select(DB::raw(
            'j.nome, jogador_gols.gols, jogador_gols.gols_sofridos,DATE_FORMAT(jogador_gols.data, "%Y-%m-%d") as data,jogador_gols.jogador_id,jogador_gols.id,jogador_gols.equipe_id,e.nome As equipe'))
        ->join('jogadores As j', 'j.id', '=', 'jogador_gols.jogador_id')
        ->leftJoin('equipes As e', 'e.id', '=', 'jogador_gols.equipe_id')
        ->where('jogador_id', $request->jogadorId)->get();
        return $jogador_gols;
    }

    public function deleteJogador(Request $request)
    {
        $jogador = Jogador::find($request->jogadorId);

        $jogador_gols = JogadorGol::where('jogador_id', $request->jogadorId);
        if($jogador_gols->get())
            $jogador_gols->delete();
        
        $jogador->delete();

        return $this->getJogadores();
    }

    public function getEquipes()
    {
        $equipes = Equipe::get();
        return $equipes;
    }

    public function getEstatisticas()
    {
        $jogador_gols = JogadorGol::select(DB::raw('day(data) as dia, month(data) as mes , year(data) as ano'))->join('equipes As e', 'e.id', '=', 'jogador_gols.equipe_id')
        ->whereIn('e.id',[3,4])
        ->groupBy('dia','mes','ano',)
        ->get();

        $data = new class{};
        $data->vitoriasTimeAzul = $this->vitoriasTimeAzul($jogador_gols);
        $data->derrotasTimeAzul = $this->derrotasTimeAzul($jogador_gols);
        $data->vitoriasTimePreto = $this->vitoriasTimePreto($jogador_gols);
        $data->derrotasTimePreto = $this->derrotasTimePreto($jogador_gols);
        $data->empates = $this->empates($jogador_gols);
        $data->golsTimeAzul = $this->golsTimeAzul();
        $data->golsTimePreto = $this->golsTimePreto();
        $data->golsTotal = $this->golsTimeAzul()+$this->golsTimePreto();
        return response()->json($data);
    }

    public function gols($dia, $mes, $ano, $equipe_id)
    {
        
        return $jogador_gols = JogadorGol::select(DB::raw('sum(gols)'))
        ->whereRaw('day(data) = '.$dia)
        ->whereRaw('month(data) = '.$mes)
        ->whereRaw('year(data) = '.$ano)
        ->where('equipe_id', $equipe_id)
        ->first();
    }

    public function vitoriasTimeAzul($jogador_gols)
    {
        $vitorias = 0;
        foreach ($jogador_gols as $value) {

            $azul = $this->gols($value->dia, $value->mes, $value->ano, 3);
            $preto = $this->gols($value->dia, $value->mes, $value->ano, 4);
            if($azul>$preto)
                $vitorias++;
        }
        return $vitorias;
    }

    public function derrotasTimeAzul($jogador_gols)
    {
        $derrotas = 0;
        foreach ($jogador_gols as $value) {

            $azul = $this->gols($value->dia, $value->mes, $value->ano, 3);
            $preto = $this->gols($value->dia, $value->mes, $value->ano, 4);
            if($azul<$preto)
                $derrotas++;
        }
        return $derrotas;
    }

    public function empates($jogador_gols)
    {
        $empates = 0;
        foreach ($jogador_gols as $value) {

            $azul = $this->gols($value->dia, $value->mes, $value->ano, 3);
            $preto = $this->gols($value->dia, $value->mes, $value->ano, 4);
            if($azul==$preto)
                $empates++;
        }
        return $empates;
    }

    public function vitoriasTimePreto($jogador_gols)
    {
        $vitorias = 0;
        foreach ($jogador_gols as $value) {

            $azul = $this->gols($value->dia, $value->mes, $value->ano, 3);
            $preto = $this->gols($value->dia, $value->mes, $value->ano, 4);
            if($azul<$preto)
                $vitorias++;
        }
        return $vitorias;
    }

    public function derrotasTimePreto($jogador_gols)
    {
        $derrotas = 0;
        foreach ($jogador_gols as $value) {

            $azul = $this->gols($value->dia, $value->mes, $value->ano, 3);
            $preto = $this->gols($value->dia, $value->mes, $value->ano, 4);
            if($azul>$preto)
                $derrotas++;
        }
        return $derrotas;
    }

    public function golsTimeAzul()
    {
        $gols = 0;
        $quauntidadeGolsMarcados = JogadorGol::select(DB::raw('sum(gols) as gols'))->where('equipe_id', 3)->first();
        $quauntidadeGolsContraMarcados = JogadorGol::select(DB::raw('sum(gol_contra) as gol_contra'))->where('equipe_id', 4)->first();
        if($quauntidadeGolsContraMarcados)
            $gols = $quauntidadeGolsMarcados->gols+$quauntidadeGolsContraMarcados->gol_contra;
        else
            $gols = $quauntidadeGolsMarcados->gols;
        return $gols;
    }

    public function golsTimePreto()
    {
        $gols = 0;
        $quauntidadeGolsMarcados = JogadorGol::select(DB::raw('sum(gols) as gols'))->where('equipe_id', 4)->first();
        $quauntidadeGolsContraMarcados = JogadorGol::select(DB::raw('sum(gol_contra) as gol_contra'))->where('equipe_id', 3)->first();
        if($quauntidadeGolsContraMarcados)
            $gols = $quauntidadeGolsMarcados->gols+$quauntidadeGolsContraMarcados->gol_contra;
        else
            $gols = $quauntidadeGolsMarcados->gols;
        return $gols;
    }

}
