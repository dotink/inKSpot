Hello <%= fHTML::encode($this->pull('name')) %>,

Thanks for signing up for an inKSpot account.  To create your account you can
follow the link below:

<%= fURL::getDomain() . $this->pull('path') %>
