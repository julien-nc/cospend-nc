* [Introduction](#s1)
  * [What is a project :paperclip: ?](#s1-1)
  * [What are balances :balance_scale: ?](#s1-2)
  * [What is a member :ok_woman: ?](#s1-3)
  * [What is a bill :dollar: ?](#s1-4)
* [Add a project](#s2)
  * [Guest access](#s2-1)
    * [Guest access permissions](#s2-1-1)
* [Add a member](#s3)
* [Add a bill](#s4)
* [Project statistics](#s5)
* [Settle the project](#s6)
* [Anonymous project creation](#s7)

# <a id='s1' />Introduction

Things you should know:

* "Sometimes small tools save big time" (:blond_haired_person: MacGyver)
* Most (all?) fields are mandatory in Cospend. The cold interface messages will tell you that.

## <a id='s1-1' />What is a project :paperclip: ?

A project contains members and bills. A project is a way to manage what is spent in a group of persons. It's a way to know who paid what for whom and when and who owes how much to whom.

## <a id='s1-2' />What are balances :balance_scale: ?

The balance value represents the situation of a member in a project. A positive balance indicates that the member payed more for the group than the grouped payed for them. By keeping an eye on the balance, one can stop taking care of exactly how much they owe to each project member.

If member A has a negative balance, -10 for example, it just means A owes 10$ to the group. Any payment of 10$ to the group (or a sub part of the group) will bring the balance back to zero. It does not matter who it was payed for.

All those actions have the same effect on member A's balance => bring it up:

1. Member A pays 10$ to member B
2. Member A pays 5$ to member B and 5$ to member C
3. Member A pays a 10$ cake for the whole group

The only difference is the effect on other members balances:

1. -10 in B's balance
2. -5 in B's balance and -5 in C's balance
3. -1 in each member's balance (if there are 10 members in the project)

## <a id='s1-3' />What is a member :ok_woman: ?

A member has a name, a weight and can be activated or not. When a member is disabled, it cannot be part of a new bill (as a payer or an ower). A disabled member will appear in member list until their balance reaches 0.

A member can be one real person. This is the most common case. Just add one member for each person in the group you want to manage.

A member can also be a sub-group of persons. For example, if Alice and Bob are a couple and want to be considered as one member in MoneyBuster, it is possible. Just create a member named "Alice & Bob" with a weight of 2. This way, when they are concerned by a bill payed by someone else, the member "Alice & Bob" will owe 2 shares of this bill, not one.

For example if Roger, with a weight of 1, pays a 30 euros bill which concerns Robert and "Alice & Bob", the balance of Roger is going up of 30. The bill concerns Roger (weight = 1) and "Alice & Bob" (weight = 2). The sum of the members weight is 3, this means we have to split the bill in 3 shares. Roger will owe 1 share (10 euros) and "Alice & Bob" will owe 2 shares (20 euros).

It seems simple enough to do it intuitively with a small example but it gets really complicated for a bigger one. Let the tool do the job. :eyeglasses:

## <a id='s1-4' />What is a bill :dollar: ?

A bill is a spending from one member which concerns one or more members in the project. A bill is defined by a name, an amount, a payer, a date and a list of owers.

# <a id='s2' />Add a project

When you first visit the app, there is no project yet. Well let's create one!

A project is defined by an ID, a name, a contact email address and a password. When you "add" a project. When creating a project from the web interface, the user's email address will be used as contact email address.

## <a id='s2-1' />Guest access

The project ID and password are important to provide access to people who don't have an account on the Nextcloud instance. They can visit the "Guest access link", enter the project ID and the password and have access to the project just like a regular user.

This link looks like `https://YOUR.NEXTCLOUD.ORG/index.php/apps/cospend/login` or `https://YOUR.NEXTCLOUD.ORG/index.php/apps/cospend/loginproject/PROJECT_ID` . Just put the correct values for YOUR.NEXTCLOUD.ORG and PROJECT_ID and you're good to go.

This link is accessible in projects context menu => "guest link".

## <a id='s2-1-1' />Guest access permissions

There are 4 levels of guest link permissions : 

* Viewer : read-only access
* Participant : can create, modify or delete bills
* Maintainer : same as participant, + can create, modify, deactivate or delete a project member, and can create, modify and delete categories and currencies
* Admin : same as maintainer, + can rename and delete project, can enable/disable bill deletion and auto-export, can modify categories order

# <a id='s3' />Add a member

This is pretty simple. Press "+" in the project drop-down menu and then press "add a member".

Just provide a user name and that's it. Member is added with a weight of 1 and is activated by default.

# <a id='s4' />Add a bill

Pretty simple too. Press the "new bill" button. Fill all fields and the bill will be saved automatically.

# <a id='s5' />Project statistics

Well does it need explanations ?

# <a id='s6' />Settle the project

This feature shows you a possible way to settle the bills and put everyone's balance back to 0.

# <a id='s7' />Anonymous project creation

There is a Cospend setting called "anonymous project creation" which is only accessible to Nextcloud admins in "additional settings".

This feature aims to reproduce the behaviour of IHateMoney in which there are no users, so there is a setting to allow project creation without being authenticated while your create the project.

If your Nextcloud admin enabled "anonymous project creation", then it is possible to create projects from a client (like [MoneyBuster](https://gitlab.com/eneiluj/moneybuster)) without being a Nextcloud user. An "anonymous" project will not be associated with any Nextcloud user and will therefore NOT appear in any user's Cospend project list. The only ways to access such projects are:

* with the [Cospend public login web page (also called guest access link)](#guest-access).
* with a client (like [MoneyBuster](https://gitlab.com/eneiluj/moneybuster))
