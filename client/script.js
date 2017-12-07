function toppad(){
    var ptop=$('.menu').outerHeight();
    $('.main').css('margin-top',ptop);
    return parseInt(ptop+100);
}

$('.menu').imagesLoaded(function(){toppad();});


$(document).ready(function(){
  getNewPosts();
});
/*$(window).scroll(function () {
  if ($(window).scrollTop() >= $(document).height() - $(window).height() - 10) {
    console.log('end of page');
    _base=getNewPosts(_base);
  }
});
*/

$('.filter').click(function(){
  if(!$(this).hasClass('active')){
    $('.filter').removeClass('active');
    $(this).addClass('active');
    var f=$(this).attr('data-filter');
    if(f!='*'){f='.'+f+'post';}
    $('.postcont').isotope({ filter: f });
  }
})



function getNewPosts(base){
  $.ajax({
		method: 'get',
		url: "../results.json",
		dataType: "json"
	})
	.done(function(res) {
    var newposts=[];
		//$.each(res.data, function (i,v){
    res.forEach(function(data){
      var date = new Date(data.ora*1000);
      var txt=data.text;
      if (txt!=undefined){
       var spazio = txt.indexOf(" ", 280);
       if (spazio!=-1){txt=txt.substr(0,spazio)+"...";}
      }
      var post='<div class="postblock '+data.type+'post"><div class="tit"><i class="fa fa-'+data.type+'"></i><span>'+date.getDate()+'/'+date.getMonth()+'/'+date.getFullYear()+'</span></div>';
      switch(data.type){
        case 'facebook':
          if(data.tipomedia=="photo"){
            post+='<div class="media"><a href="'+data.picture+'" target="_blank"><img src="'+data.picture+'" class="img-responsive"></a></div>';
          }
          if(data.tipomedia=="video"){
            post+='<div class="media"><a href="'+data.picture+'" target="_blank"><img src="'+data.picture+'" class="img-responsive"></a></div>';
          }
          break;
        case 'twitter':
          if(data.media!=null){
            post+='<div class="media"><a href="'+data.post_link+'" target="_blank"><img src="'+data.media+'" class="img-responsive"></a></div>';
          }
          break;
        case 'instagram':
          if(data.media!=null){
            post+='<div class="media"><a href="'+data.post_link+'" target="_blank"><img src="'+data.media+'" class="img-responsive"></a></div>';
          }
          break;
      }
      post+='<div class="txt">'+txt+'</div><a href="'+data.post_link+'" target="_blank" class="bot"><span>see original post</span><i class="fa fa-chevron-right"></i></a></div>';
      newposts.push(post);
			//$('.postcont').append(post);
    });
    $('.postcont').append(newposts);
		$('.main').imagesLoaded(function(){
      $('.postcont').isotope({itemSelector: '.postblock',layoutMode: 'packery'});
		});
	})
	.fail(function(err) {
		console.log(err);
	});
}
