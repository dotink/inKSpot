<h1>Create Your Account</h1>
<form action="" method="post">
	<% fMessaging::show('error',   fURL::get()) %>
	<% fMessaging::show('success', fURL::get()) %>
	
	<label for="username">Desired Username:</label>
	<input id="username" type="text" max_length="32" name="username" value="<%= fHTML::encode($this->pull('username', '')) %>" />

	<label for="location">Location:</label>
	<input id="location" type="text" max_length="64" name="location" value="<%= fHTML::encode($this->pull('location', '')) %>" />

	<fieldset>
		<legend>Security</legend>
		<label for="login_password">Password</label>
		<input id="login_password" type="password" name="login_password" value="" />
		<label for="confirm-login_password">Confirm</label>
		<input id="confirm-login_password" type="password" name="confirm-login_password" value="" />
	</fieldset>

	<button>Create</button>
</form>
