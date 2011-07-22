<h1>Signup. Dev Social!</h1>
<form action="" method="post">
	<% fMessaging::show('error',   fURL::get()) %>
	<% fMessaging::show('success', fURL::get()) %>
	<label for="name">Name:</label>
	<input id="name" type="text" max_length="64" name="name" value="<%= fHTML::encode($this->pull('name', '')) %>" />
	<label for="email_address">What's your E-mail Address?</label>
	<input id="email_address" type="text" max_length="256" name="email_address" value="<%= fHTML::encode($this->pull('email', '')) %>" />
	<button>Let Me In</button>
</form>
