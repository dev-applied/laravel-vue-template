<template>
  <div id="FourOhFour">
    <div id="clouds">
      <div class="cloud x1" />
      <div class="cloud x1_5" />
      <div class="cloud x2" />
      <div class="cloud x3" />
      <div class="cloud x4" />
      <div class="cloud x5" />
    </div>
    <div class="c">
      <div class="_404">
        404
      </div>
      <hr>
      <div class="_1">
        THE PAGE
      </div>
      <div class="_2">
        WAS NOT FOUND
      </div>
      <router-link class="btn" to="/dashboard">
        BACK TO DASHBOARD
      </router-link>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'

export default defineComponent({
  name: 'ErrorFourOhFour',
  mounted () {
    document.querySelector('body')!.classList.add('not-found')
  },
  beforeDestroy () {
    document.querySelector('body')!.classList.remove('not-found')
  }
})
</script>

<style>
  body.not-found{
    background: #33cc99 !important;
    color:#fff;
    font-family: 'Open Sans', sans-serif;
    max-height:700px;
    overflow: hidden;
  }

  .not-found .theme--light.v-application {
    background: transparent !important;
  }
</style>

<style scoped>

  .c{
    text-align: center;
    display: block;
    position: relative;
    width:80%;
    margin:100px auto;
  }
  ._404{
    font-size: 220px;
    position: relative;
    display: inline-block;
    z-index: 2;
    height: 250px;
    letter-spacing: 15px;
  }
  ._1{
    text-align:center;
    display:block;
    position:relative;
    letter-spacing: 12px;
    font-size: 4em;
    line-height: 80%;
  }
  ._2{
    text-align:center;
    display:block;
    position: relative;
    font-size: 20px;
  }

  .btn{
    background-color: rgb( 255, 255, 255 );
    position: relative;
    display: inline-block;
    width: 358px;
    padding: 5px;
    z-index: 5;
    font-size: 25px;
    color:#33cc99;
    text-decoration: none;
    margin: 0 10px 0 auto;
  }

  hr{
    padding: 0;
    border: none;
    border-top: 5px solid #fff;
    color: #fff;
    text-align: center;
    margin: 0 auto;
    width: 420px;
    height:10px;
    z-index: -10;
  }

  .cloud {
    width: 350px; height: 120px;

    background: #FFF;
    background-image: linear-gradient(to bottom , #FFF 100%);
    background-image: -webkit-linear-gradient(to bottom , #FFF 100%);
    background-image: -moz-linear-gradient(to bottom , #FFF 100%);
    background-image: -ms-linear-gradient(to bottom , #FFF 100%);
    background-image: -o-linear-gradient(to bottom , #FFF 100%);

    border-radius: 100px;
    -webkit-border-radius: 100px;
    -moz-border-radius: 100px;

    position: absolute;
    margin: 120px auto 20px;
    z-index:-1;
    transition: ease 1s;
  }

  .cloud:after, .cloud:before {
    content: '';
    position: absolute;
    background: #FFF;
    z-index: -1
  }

  .cloud:after {
    width: 100px; height: 100px;
    top: -50px; left: 50px;

    border-radius: 100px;
    -webkit-border-radius: 100px;
    -moz-border-radius: 100px;
  }

  .cloud:before {
    width: 180px; height: 180px;
    top: -90px; right: 50px;

    border-radius: 200px;
    -webkit-border-radius: 200px;
    -moz-border-radius: 200px;
  }

  .x1 {
    top:-50px;
    left:100px;
    -webkit-transform: scale(0.3);
    -moz-transform: scale(0.3);
    transform: scale(0.3);
    opacity: 0.9;
    -webkit-animation: moveclouds 15s linear infinite;
    -moz-animation: moveclouds 15s linear infinite;
    -o-animation: moveclouds 15s linear infinite;
  }

  .x1_5{
    top:-80px;
    left:250px;
    -webkit-transform: scale(0.3);
    -moz-transform: scale(0.3);
    transform: scale(0.3);
    -webkit-animation: moveclouds 17s linear infinite;
    -moz-animation: moveclouds 17s linear infinite;
    -o-animation: moveclouds 17s linear infinite;
  }

  .x2 {
    left: 250px;
    top:30px;
    -webkit-transform: scale(0.6);
    -moz-transform: scale(0.6);
    transform: scale(0.6);
    opacity: 0.6;
    -webkit-animation: moveclouds 25s linear infinite;
    -moz-animation: moveclouds 25s linear infinite;
    -o-animation: moveclouds 25s linear infinite;
  }

  .x3 {
    left: 250px; bottom: -70px;

    -webkit-transform: scale(0.6);
    -moz-transform: scale(0.6);
    transform: scale(0.6);
    opacity: 0.8;

    -webkit-animation: moveclouds 25s linear infinite;
    -moz-animation: moveclouds 25s linear infinite;
    -o-animation: moveclouds 25s linear infinite;
  }

  .x4 {
    left: 470px;
    bottom: 20px;
    -webkit-transform: scale(0.75);
    -moz-transform: scale(0.75);
    transform: scale(0.75);
    opacity: 0.75;

    -webkit-animation: moveclouds 18s linear infinite;
    -moz-animation: moveclouds 18s linear infinite;
    -o-animation: moveclouds 18s linear infinite;
  }

  .x5 {
    left: 200px; top: 300px;

    -webkit-transform: scale(0.5);
    -moz-transform: scale(0.5);
    transform: scale(0.5);
    opacity: 0.8;

    -webkit-animation: moveclouds 20s linear infinite;
    -moz-animation: moveclouds 20s linear infinite;
    -o-animation: moveclouds 20s linear infinite;
  }

  @-webkit-keyframes moveclouds {
    0% {margin-left: 1000px;}
    100% {margin-left: -1000px;}
  }
  @-moz-keyframes moveclouds {
    0% {margin-left: 1000px;}
    100% {margin-left: -1000px;}
  }
  @-o-keyframes moveclouds {
    0% {margin-left: 1000px;}
    100% {margin-left: -1000px;}
  }
</style>
