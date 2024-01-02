* [Introduction](#s1)
  * [What is a project :paperclip: ?](#s1-1)
  * [What are balances :balance_scale: ?](#s1-2)
  * [What is a member :ok_woman: ?](#s1-3)
  * [What is a bill :dollar: ?](#s1-4)
* [Create a project](#s2)
  * [Shared access](#s2-1)
    * [Share link permissions](#s2-1-1)
* [Create a member](#s3)
* [Create a bill](#s4)
* [Project statistics](#s5)
* [Settle the project](#s6)

# <a id='s1' />Introduction

Things you should know:

* "Sometimes small tools save big time" (:blond_haired_person: MacGyver)
* Most (all?) fields are mandatory in Cospend. The cold interface messages will tell you that.

## <a id='s1-1' />What is a project :paperclip: ?

A project contains members and bills. A project is a way to manage what is spent in a group of persons.
It's a way to know who paid what for whom and when and who owes how much to the group.
Debts are not personal, a member who has a debt in the group (negative balance) can pay anyone in the group
to bring his/her balance back to zero and leave the group. This will have an effect on other's balances.

## <a id='s1-2' />What are balances :balance_scale: ?

The balance value represents the situation of a member in a project.
A positive balance indicates that the member payed more for the group than the grouped payed for them.
By keeping an eye on the balance, one can stop taking care of exactly how much they owe to each project member.

If member A has a negative balance, -10 for example, it just means A owes 10$ to the group.
Any payment of 10$ to the group (or a sub part of the group) will bring the balance back to zero.
It does not matter who it was payed for.

All those actions have the same effect on the member A's balance => Make it raise of 10:

0. Member A pays 20$ for a cake that is eaten by A and B (one bill payed by A with A and B as owers)
1. Member A pays 10$ to member B (one bill payed by A with B as ower)
2. Member A pays 5$ to member B and 5$ to member C (one bill payed by A with B as ower, another bill payed by A with C as ower)
3. Member A pays a 15$ cake eaten by A, B and C (on bill payed by A with A, B and C as owers)

The only difference is the effect on other members balances:

0. -10 in B's balance
1. -10 in B's balance
2. -5 in B's balance and -5 in C's balance
3. -5 in each ower's balance

## <a id='s1-3' />What is a member :ok_woman: ?

A member has a name, a weight and can be activated or not. When a member is disabled, it cannot be part of a new bill (as a payer or an ower). A disabled member will appear in member list until their balance reaches 0.

A member can be one real person. This is the most common case. Just add one member for each person in the group you want to manage.

A member can also be a sub-group of persons. For example, if Alice and Bob are a couple and want to be considered as one member in MoneyBuster, it is possible. Just create a member named "Alice & Bob" with a weight of 2. This way, when they are concerned by a bill payed by someone else, the member "Alice & Bob" will owe 2 shares of this bill, not one.

For example if Roger, with a weight of 1, pays a 30 euros bill which concerns Robert and "Alice & Bob", the balance of Roger is going up of 30. The bill concerns Roger (weight = 1) and "Alice & Bob" (weight = 2). The sum of the members weight is 3, this means we have to split the bill in 3 shares. Roger will owe 1 share (10 euros) and "Alice & Bob" will owe 2 shares (20 euros).

It seems simple enough to do it intuitively with a small example but it gets really complicated for a bigger one. Let the tool do the job. :eyeglasses:

## <a id='s1-4' />What is a bill :dollar: ?

A bill is a spending from one member which concerns one or more members in the project. A bill is defined by a name, an amount, a payer, a date and a list of owers.

# <a id='s2' />Create a project

When you first visit the app, there is no project yet.

A project is defined by an ID and a name.

Cospend and SplitWise CSV project files can be imported in Cospend.

## <a id='s2-1' />Shared access

Cospend lets you share your projects with users, groups and circles.
It is also possible to create public share links to share a project with people who don't have an account on your Nextcloud instance.
Public share links can be password protected.

## <a id='s2-1-1' />Share link permissions

There are 4 permission levels for shared links: 

* Viewer: read-only access
* Participant: can create, modify or delete bills
* Maintainer: same as participant + can create, modify, disable or delete a project member + can create, modify and delete categories, payment modes and currencies
* Admin: same as maintainer + can rename and delete project + can toggle bill deletion and auto-export + can modify categories or payment modes order

# <a id='s3' />Create a member

This is pretty simple. Press "+" in the project drop-down menu and then press "add a member".

Just provide a user name and that's it. Member is added with a weight of 1 and is activated by default.

# <a id='s4' />Create a bill

Pretty simple too. Press the "new bill" button. Fill all fields and press the "Save bill" button.

# <a id='s5' />Project statistics

The filters on top of the statistics page apply to all the statistics charts and tables.

# <a id='s6' />Settle the project

This feature gives you an optimal project settlement/reimbursement plan to put everyone's balance back to 0.
