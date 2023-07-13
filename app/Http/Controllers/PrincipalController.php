<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Jogador;
use App\Models\JogadorGol;
use App\Models\Equipe;
use App\Models\Temporadas;
use DB;

class PrincipalController extends Controller
{
    public function index()
    {
        // $jogadores = $this->getJogadores();
        $equipes = $this->getEquipes();
        $temporadas = $this->getTemporadas();
        return view('index',compact('equipes', 'temporadas'));
    }

    public function store(Request $request){
        $jogador = new Jogador();
        $jogador->nome = $request->nome;
        $jogador->save();
        return $this->getJogadores($request);
    }

    public function update(Request $request){
        $jogador = Jogador::find($request->id);
        $jogador->nome = $request->nome;
        $jogador->update();
        return $this->getJogadores($request);
    }

    public function getJogadores(Request $request)
    {
        // $jogadores = Jogador::select(DB::raw('jogadores.id, jogadores.nome, sum(jg.gols) as gols, sum(jg.gols_sofridos) as gols_sofridos, sum(gol_contra) as gol_contra'))
        // ->leftJoin('jogador_gols As jg', 'jg.jogador_id', '=', 'jogadores.id');

        // if($request->temporada)
        //     $jogadores = $jogadores->where('temporada_id', $request->temporada);
        
        // $jogadores = $jogadores->groupBy('jogadores.id')
        // ->orderBy('gols', 'desc')
        // ->get();
        // return $jogadores;


        $jogadores = Jogador::get();
        foreach ($jogadores as $jogador) {
            $jogador_estatistica = $this->getJogadorEstatisticas($jogador->id, $request->temporada_id);
            $jogador->gols = $jogador_estatistica->gols;
            $jogador->gols_sofridos = $jogador_estatistica->gols_sofridos;
            $jogador->gol_contra = $jogador_estatistica->gol_contra;
        }
        
        return $jogadores->sortByDesc('gols')->values();
    }

    public function storeGol(Request $request)
    {
        $gols = new JogadorGol();
        $gols->jogador_id = $request->jogadorId;
        $gols->equipe_id = $request->equipe_id;
        $gols->gols = $request->gols;
        $gols->gols_sofridos = $request->gols_sofridos;
        $gols->data = $request->data;
        $gols->temporada_id = $request->temporada_id;
        $gols->save();

        $data = new class{};
        $data->jogador_gols = $this->getGolsJogador($request);
        $data->jogadores = $this->getJogadores($request);
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
        $jogadorGol->temporada_id = $request->temporada_id;
        $jogadorGol->update();

        $data = new class{};
        $data->jogador_gols = $this->getGolsJogador($request);
        $data->jogadores = $this->getJogadores($request);
        return response()->json($data);

    }

    public function getGolsJogador(Request $request)
    {
        $jogador_gols = JogadorGol::select(DB::raw(
            'j.nome, jogador_gols.gols, jogador_gols.gols_sofridos,DATE_FORMAT(jogador_gols.data, "%Y-%m-%d") as data,jogador_gols.jogador_id,jogador_gols.id,jogador_gols.equipe_id,e.nome As equipe, jogador_gols.temporada_id'))
        ->join('jogadores As j', 'j.id', '=', 'jogador_gols.jogador_id')
        ->leftJoin('equipes As e', 'e.id', '=', 'jogador_gols.equipe_id')
        ->where('jogador_id', $request->jogadorId);

        if($request->temporada_id)
            $jogador_gols = $jogador_gols->where('temporada_id', $request->temporada_id);

        $jogador_gols = $jogador_gols->get();
        return $jogador_gols;
    }

    public function deleteJogador(Request $request)
    {
        $jogador = Jogador::find($request->jogadorId);

        $jogador_gols = JogadorGol::where('jogador_id', $request->jogadorId);
        if($jogador_gols->get())
            $jogador_gols->delete();
        
        $jogador->delete();

        return $this->getJogadores($request);
    }

    public function getEquipes()
    {
        $equipes = Equipe::get();
        return $equipes;
    }

    public function getEstatisticas(Request $request)
    {
        $jogador_gols = JogadorGol::select(DB::raw('day(data) as dia, month(data) as mes , year(data) as ano, temporada_id'))->join('equipes As e', 'e.id', '=', 'jogador_gols.equipe_id')
        ->whereIn('e.id',[3,4]);

        if($request->temporada_id)
            $jogador_gols = $jogador_gols->where('temporada_id', $request->temporada_id);

        $jogador_gols = $jogador_gols->groupBy('dia','mes','ano', 'temporada_id')->get();

        $data = new class{};
        $data->vitoriasTimeAzul = $this->vitoriasTimeAzul($jogador_gols);
        $data->derrotasTimeAzul = $this->derrotasTimeAzul($jogador_gols);
        $data->vitoriasTimePreto = $this->vitoriasTimePreto($jogador_gols);
        $data->derrotasTimePreto = $this->derrotasTimePreto($jogador_gols);
        $data->empates = $this->empates($jogador_gols);
        $data->golsTimeAzul = $this->golsTimeAzul($request->temporada_id);
        $data->golsTimePreto = $this->golsTimePreto($request->temporada_id);
        $data->golsTotal = $data->golsTimeAzul+$data->golsTimePreto;
        return response()->json($data);
    }

    public function gols($dia, $mes, $ano, $equipe_id, $temporada_id)
    {
        
        return $jogador_gols = JogadorGol::select(DB::raw('sum(gols)'))
        ->whereRaw('day(data) = '.$dia)
        ->whereRaw('month(data) = '.$mes)
        ->whereRaw('year(data) = '.$ano)
        ->where('equipe_id', $equipe_id)
        ->where('temporada_id', $temporada_id)
        ->first();
    }

    public function vitoriasTimeAzul($jogador_gols)
    {
        $vitorias = 0;
        foreach ($jogador_gols as $value) {

            $azul = $this->gols($value->dia, $value->mes, $value->ano, 3, $value->temporada_id);
            $preto = $this->gols($value->dia, $value->mes, $value->ano, 4, $value->temporada_id);
            if($azul>$preto)
                $vitorias++;
        }
        return $vitorias;
    }

    public function derrotasTimeAzul($jogador_gols)
    {
        $derrotas = 0;
        foreach ($jogador_gols as $value) {

            $azul = $this->gols($value->dia, $value->mes, $value->ano, 3, $value->temporada_id);
            $preto = $this->gols($value->dia, $value->mes, $value->ano, 4, $value->temporada_id);
            if($azul<$preto)
                $derrotas++;
        }
        return $derrotas;
    }

    public function empates($jogador_gols)
    {
        $empates = 0;
        foreach ($jogador_gols as $value) {

            $azul = $this->gols($value->dia, $value->mes, $value->ano, 3, $value->temporada_id);
            $preto = $this->gols($value->dia, $value->mes, $value->ano, 4, $value->temporada_id);
            if($azul==$preto)
                $empates++;
        }
        return $empates;
    }

    public function vitoriasTimePreto($jogador_gols)
    {
        $vitorias = 0;
        foreach ($jogador_gols as $value) {

            $azul = $this->gols($value->dia, $value->mes, $value->ano, 3, $value->temporada_id);
            $preto = $this->gols($value->dia, $value->mes, $value->ano, 4, $value->temporada_id);
            if($azul<$preto)
                $vitorias++;
        }
        return $vitorias;
    }

    public function derrotasTimePreto($jogador_gols)
    {
        $derrotas = 0;
        foreach ($jogador_gols as $value) {

            $azul = $this->gols($value->dia, $value->mes, $value->ano, 3, $value->temporada_id);
            $preto = $this->gols($value->dia, $value->mes, $value->ano, 4, $value->temporada_id);
            if($azul>$preto)
                $derrotas++;
        }
        return $derrotas;
    }

    public function golsTimeAzul($temporada_id)
    {
        $gols = 0;
        $quauntidadeGolsMarcados = JogadorGol::select(DB::raw('sum(gols) as gols'))
        ->where('equipe_id', 3);
        if($temporada_id)
            $quauntidadeGolsMarcados = $quauntidadeGolsMarcados->where('temporada_id', $temporada_id);

        $quauntidadeGolsMarcados = $quauntidadeGolsMarcados->first();

        $quauntidadeGolsContraMarcados = JogadorGol::select(DB::raw('sum(gol_contra) as gol_contra'))
        ->where('equipe_id', 4);
        if($temporada_id)
            $quauntidadeGolsContraMarcados = $quauntidadeGolsContraMarcados->where('temporada_id', $temporada_id);

        $quauntidadeGolsContraMarcados = $quauntidadeGolsContraMarcados->first();

        if($quauntidadeGolsContraMarcados)
            $gols = $quauntidadeGolsMarcados->gols+$quauntidadeGolsContraMarcados->gol_contra;
        else
            $gols = $quauntidadeGolsMarcados->gols;
        return $gols;
    }

    public function golsTimePreto($temporada_id)
    {
        $gols = 0;
        $quauntidadeGolsMarcados = JogadorGol::select(DB::raw('sum(gols) as gols'))
        ->where('equipe_id', 4);
        if($temporada_id)
            $quauntidadeGolsMarcados = $quauntidadeGolsMarcados->where('temporada_id', $temporada_id);

        $quauntidadeGolsMarcados = $quauntidadeGolsMarcados->first();

        $quauntidadeGolsContraMarcados = JogadorGol::select(DB::raw('sum(gol_contra) as gol_contra'))
        ->where('equipe_id', 3);
        if($temporada_id)
            $quauntidadeGolsContraMarcados = $quauntidadeGolsContraMarcados->where('temporada_id', $temporada_id);

        $quauntidadeGolsContraMarcados = $quauntidadeGolsContraMarcados->first();
        
        if($quauntidadeGolsContraMarcados)
            $gols = $quauntidadeGolsMarcados->gols+$quauntidadeGolsContraMarcados->gol_contra;
        else
            $gols = $quauntidadeGolsMarcados->gols;
        return $gols;
    }

    public function getTemporadas() {
        $temporadas = Temporadas::select('id', 'nome')->get();
        return $temporadas;
    }

    public function getJogadorEstatisticas($jogador_id, $temporada_id) {
        $jogador_gols = JogadorGol::select(DB::raw('sum(gols) as gols, sum(gols_sofridos) as gols_sofridos, sum(gol_contra) as gol_contra'))
        ->where('jogador_id', $jogador_id);
        if($temporada_id)
            $jogador_gols = $jogador_gols->where('temporada_id', $temporada_id);
        $jogador_gols = $jogador_gols->first();

        return $jogador_gols;
    }

}
