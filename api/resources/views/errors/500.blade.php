@extends('errors::minimal')

@section('title', 'Something went wrong')
@section('code', '500')
@section('message', 'Aiyo, something broke on our side.')
@section('detail', "It's not you. Give it a minute, then try again.")
